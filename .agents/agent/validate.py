"""validate.py — live smoke test for the token-efficient agent core.

Run:  python agent/validate.py
Asserts: memory window <=4 msgs, summary <=cap, retrieved payload <=800 tokens,
tool schemas are minimal and valid JSON. Prints measured token costs.
"""
from __future__ import annotations

import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from context_retriever import CONTEXT_TOKEN_BUDGET, Retriever          # noqa: E402
from memory_manager import MemoryManager                               # noqa: E402
from prompts_config import PROMPT_TOKENS, get_prompt                   # noqa: E402
from tokens import count_tokens, json_tokens                           # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))


def test_memory():
    m = MemoryManager(max_summary_tokens=120, summarize_after=3)
    sys_p = get_prompt("system_core")
    for i in range(12):
        m.add("user" if i % 2 == 0 else "assistant", f"Message {i} with some detail about order {i}.")
    msgs = m.build_messages(sys_p)
    # 1 system + (<=1 summary) + last 4 window
    assert len(msgs) <= 6, f"payload too large: {len(msgs)} messages"
    assert len(m.window) == 4, f"window must be 4, got {len(m.window)}"
    assert count_tokens(m.summary) <= 120, "summary exceeded cap"
    pt = m.payload_tokens(sys_p)
    print(f"[memory] window=4  summary_tokens={count_tokens(m.summary)}  payload_tokens={pt}")
    return msgs, pt


def test_retriever():
    docs = ("MeroBhoj is a restaurant ordering platform. " * 40) + (
        "Customers scan a QR code to open a menu. Orders flow: new -> cooking -> "
        "ready -> delivered. eSewa handles payments. Riders see delivery jobs. "
    ) * 30
    r = Retriever([docs])
    payload = r.retrieve("how do orders and payments work", budget_tokens=CONTEXT_TOKEN_BUDGET)
    t = count_tokens(payload)
    assert t <= CONTEXT_TOKEN_BUDGET, f"payload {t} > {CONTEXT_TOKEN_BUDGET}"
    # even a giant corpus with everything relevant must stay <= 800
    payload2 = r.retrieve("QR menu esewa rider delivery", budget_tokens=CONTEXT_TOKEN_BUDGET)
    t2 = count_tokens(payload2)
    assert t2 <= CONTEXT_TOKEN_BUDGET
    print(f"[retriever] chunks={len(r.chunks)}  payload1_tokens={t}  payload2_tokens={t2} (cap {CONTEXT_TOKEN_BUDGET})")


def test_configs():
    with open(os.path.join(HERE, "minified_tools.json"), encoding="utf-8") as f:
        cfg = json.load(f)
    for tool in cfg["tools"]:
        fn = {"type": "function", "function": {
            "name": tool["name"], "description": tool["description"],
            "parameters": tool["parameters"]}}
        n = json_tokens(fn)
        assert n < 100, f"{tool['name']} schema too fat: {n} tokens"
        print(f"[tools] {tool['name']}: {n} tokens")
    print(f"[prompts] state prompts: {PROMPT_TOKENS} tokens total")


if __name__ == "__main__":
    test_memory()
    test_retriever()
    test_configs()
    print("ALL CHECKS PASSED")
