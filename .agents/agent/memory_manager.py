"""memory_manager.py — sliding-window memory with rolling summarization.

Policy (strict token budgeting):
  * Hot window  = last N (default 4) messages, sent verbatim.
  * Evicted     = anything older moves to a pending buffer.
  * Summary     = when the pending buffer exceeds `summarize_after`
                  messages, it is folded into a single running summary
                  capped at `max_summary_tokens`.
Payload sent to the LLM: [system] + [summary] + [last 4 messages]. Nothing else.
"""
from __future__ import annotations

from typing import Callable, List, Optional

from tokens import Budget, count_message_tokens, count_tokens, truncate_to_tokens

WINDOW_SIZE = 4  # verbatim messages kept


def _extractive_summary(text: str, max_tokens: int) -> str:
    """Offline fallback: keep highest-signal first sentences within budget."""
    budget = Budget(max_tokens)
    keep: List[str] = []
    for sent in text.replace("\n", " ").split(". "):
        sent = sent.strip()
        if not sent:
            continue
        t = count_tokens(sent) + 1
        if not budget.can_fit(t):
            break
        budget.count(t)
        keep.append(sent)
    return ". ".join(keep) or truncate_to_tokens(text, max_tokens)


class MemoryManager:
    def __init__(
        self,
        summarize_fn: Optional[Callable[[str], str]] = None,
        max_summary_tokens: int = 150,
        summarize_after: int = 4,  # fold pending buffer every N evictions
    ):
        self.window: List[dict] = []            # last N messages (verbatim)
        self.pending: List[str] = []            # evicted, awaiting summary
        self.summary: str = ""                  # rolling older-context summary
        self.summarize_fn = summarize_fn or (
            lambda text: _extractive_summary(text, self.max_summary_tokens)
        )
        self.max_summary_tokens = max_summary_tokens
        self.summarize_after = summarize_after

    # -- ingestion ---------------------------------------------------------
    def add(self, role: str, content: str) -> None:
        self.window.append({"role": role, "content": content})
        if len(self.window) > WINDOW_SIZE:
            old = self.window.pop(0)
            self.pending.append(f"{old['role']}: {old['content']}")
            if len(self.pending) >= self.summarize_after:
                self._roll_summary()

    def _roll_summary(self) -> None:
        blob = " | ".join(self.pending)
        self.pending.clear()
        merged = f"{self.summary} NEW: {blob}" if self.summary else blob
        new_summary = self.summarize_fn(merged)
        # keep summary newest-first informative; hard-cap tokens either way
        self.summary = truncate_to_tokens(new_summary, self.max_summary_tokens)

    # -- export ------------------------------------------------------------
    def build_messages(self, system_prompt: str) -> List[dict]:
        """Exactly what the LLM should receive. Deterministic, bounded size."""
        msgs: List[dict] = [{"role": "system", "content": system_prompt}]
        if self.summary:
            msgs.append(
                {"role": "system", "content": f"Earlier context: {self.summary}"}
            )
        msgs.extend(self.window)
        return msgs

    def payload_tokens(self, system_prompt: str) -> int:
        return sum(count_message_tokens(m["role"], m["content"])
                   for m in self.build_messages(system_prompt))

    def reset(self) -> None:
        self.window.clear()
        self.pending.clear()
        self.summary = ""
