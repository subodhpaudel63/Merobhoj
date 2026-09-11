"""context_retriever.py — RAG utility with a HARD 800-token context payload.

Pipeline: chunk -> score -> pack.
  * chunk_text  : token-aware chunks with small overlap (no mid-sentence waste).
  * retrieve    : keyword-scored ranking (BM25-lite), zero dependencies.
  * pack        : greedily fills the budget; the last chunk is TRUNCATED,
                  never dropped silently and never exceeding the cap.
Retrieved payload is always <= CONTEXT_TOKEN_BUDGET (800).
"""
from __future__ import annotations

import math
import re
from dataclasses import dataclass
from typing import Dict, List, Sequence

from tokens import Budget, count_tokens, truncate_to_tokens

CONTEXT_TOKEN_BUDGET = 800  # absolute cap on retrieved payload
CHUNK_TOKENS = 120          # chunk target size
CHUNK_OVERLAP = 15          # token overlap between consecutive chunks
STOPWORDS = frozenset(
    "a an the and or of to in on for with is are was were be been it this that "
    "as at by from into about over after before you your i we they he she do does"
    .split()
)

_WORD_RE = re.compile(r"[a-z0-9]+")


@dataclass
class Chunk:
    id: int
    text: str
    tokens: int


def chunk_text(text: str, chunk_tokens: int = CHUNK_TOKENS,
               overlap: int = CHUNK_OVERLAP) -> List[Chunk]:
    """Token-aware sliding chunker with overlap, split on sentence bounds."""
    sentences = re.split(r"(?<=[.!?])\s+", text.strip())
    chunks: List[Chunk] = []
    buf: List[str] = []
    buf_tokens = 0
    cid = 0

    def flush():
        nonlocal buf, buf_tokens, cid
        if buf:
            joined = " ".join(buf)
            chunks.append(Chunk(cid, joined, count_tokens(joined)))
            cid += 1
            buf, buf_tokens = [], 0

    for sent in sentences:
        t = count_tokens(sent) + 1
        if t > chunk_tokens:  # single huge sentence: hard split
            flush()
            pieces = truncate_to_tokens(sent, chunk_tokens)
            chunks.append(Chunk(cid, pieces, count_tokens(pieces)))
            cid += 1
            continue
        if buf_tokens + t > chunk_tokens:
            # capture an overlap tail BEFORE flushing
            prev_text = " ".join(buf)
            flush()
            if prev_text:
                tail_words = " ".join(prev_text.split()[-6:])  # ~15 tokens
                if count_tokens(tail_words) <= overlap:
                    buf.append(tail_words)
                    buf_tokens = count_tokens(tail_words) + 1
        buf.append(sent)
        buf_tokens += t
    flush()
    return chunks


def _terms(text: str) -> List[str]:
    return [w for w in _WORD_RE.findall(text.lower()) if w not in STOPWORDS]


class Retriever:
    """BM25-lite retriever. Fit once, query many, pack to budget."""

    def __init__(self, docs: Sequence[str], k1: float = 1.2, b: float = 0.75):
        self.chunks = chunk_text("\n".join(docs)) if len(docs) == 1 else [
            Chunk(i, d, count_tokens(d)) for i, d in enumerate(docs)
        ]
        self.k1, self.b = k1, b
        term_lists = [_terms(c.text) for c in self.chunks]
        self.tf: List[Dict[str, int]] = []
        for terms in term_lists:
            freqs: Dict[str, int] = {}
            for w in terms:
                freqs[w] = freqs.get(w, 0) + 1
            self.tf.append(freqs)
        self.dl = [max(1, len(t)) for t in term_lists]
        self.avgdl = sum(self.dl) / len(self.dl)
        df: Dict[str, int] = {}
        for freqs in self.tf:
            for w in freqs:
                df[w] = df.get(w, 0) + 1
        self.idf = {w: math.log(1 + (len(self.chunks) - n + 0.5) / (n + 0.5))
                    for w, n in df.items()}

    def search(self, query: str, top_k: int = 6) -> List[tuple]:
        q = _terms(query)
        scores = []
        for i, freqs in enumerate(self.tf):
            s = 0.0
            for w in q:
                if w in freqs:
                    f = freqs[w]
                    norm = self.k1 * (1 - self.b + self.b * self.dl[i] / self.avgdl)
                    s += self.idf.get(w, 0.0) * f * (self.k1 + 1) / (f + norm)
            if s > 0:
                scores.append((s, i))
        scores.sort(reverse=True)
        return scores[:top_k]

    def retrieve(self, query: str, budget_tokens: int = CONTEXT_TOKEN_BUDGET,
                 top_k: int = 6) -> str:
        """Pack top chunks into a payload strictly within budget_tokens."""
        budget = Budget(budget_tokens)
        parts: List[str] = []
        for _, i in self.search(query, top_k=top_k):
            c = self.chunks[i]
            piece = f"[{c.id}] {c.text}"
            t = count_tokens(piece)
            if budget.can_fit(t):
                budget.count(t)
                parts.append(piece)
            else:
                fits = budget.remaining - 1  # header overhead
                if fits > 10:
                    parts.append(truncate_to_tokens(piece, fits))
                break  # budget exhausted — stop, never overflow
        return "\n".join(parts)
