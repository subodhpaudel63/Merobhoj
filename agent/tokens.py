"""tokens.py — single source of truth for token accounting.

Zero dependencies. Uses tiktoken for exact counts when available,
otherwise a chars/4 heuristic (accurate enough for budgeting).
"""
from __future__ import annotations

import json

try:  # optional exact tokenizer
    import tiktoken

    _ENC = tiktoken.get_encoding("cl100k_base")

    def count_tokens(text: str) -> int:
        return len(_ENC.encode(text))

except Exception:  # pragma: no cover - fallback path

    def count_tokens(text: str) -> int:
        return max(1, (len(text) + 3) // 4)


def count_message_tokens(role: str, content: str) -> int:
    """Tokens for one chat message incl. role/separator overhead (~4)."""
    return count_tokens(content) + count_tokens(role) + 4


def json_tokens(obj) -> int:
    """Tokens of a compact JSON serialization (for tool schemas/payloads)."""
    return count_tokens(json.dumps(obj, ensure_ascii=False, separators=(",", ":")))


class Budget:
    """Hard token budget. count() raises once exceeded — fail loud, never bloat."""

    __slots__ = ("limit", "used")

    def __init__(self, limit: int):
        self.limit = limit
        self.used = 0

    def can_fit(self, n: int) -> bool:
        return self.used + n <= self.limit

    def count(self, n: int) -> int:
        if not self.can_fit(n):
            raise OverflowError(f"token budget {self.limit} exceeded ({self.used}+{n})")
        self.used += n
        return n

    @property
    def remaining(self) -> int:
        return self.limit - self.used


def truncate_to_tokens(text: str, max_tokens: int) -> str:
    """Hard-truncate text to fit max_tokens (fallback path only)."""
    if count_tokens(text) <= max_tokens:
        return text
    lo, hi = 0, len(text)
    while lo < hi:  # binary search smallest prefix that fits
        mid = (lo + hi + 1) // 2
        if count_tokens(text[:mid]) <= max_tokens:
            lo = mid
        else:
            hi = mid - 1
    return text[:lo].rstrip()
