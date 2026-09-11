"""prompts_config.py — loader for prompts_config.json.

Loads lazily and caches. Only the state prompt you request ever enters
the context window — never the whole config.
"""
from __future__ import annotations

import json
import os
from functools import lru_cache

from tokens import count_tokens

HERE = os.path.dirname(os.path.abspath(__file__))
_PATH = os.path.join(HERE, "prompts_config.json")


@lru_cache(maxsize=1)
def load_prompts() -> dict:
    with open(_PATH, encoding="utf-8") as f:
        return json.load(f)


def get_prompt(state: str, **vars) -> str:
    p = load_prompts()["states"][state]
    return p.format(**vars) if vars else p


@lru_cache(maxsize=1)
def prompt_token_report() -> dict:
    states = load_prompts()["states"]
    return {k: count_tokens(v) for k, v in states.items()}


PROMPT_TOKENS = sum(prompt_token_report().values())
