"""Zhuštěný průběh relace ze stream-json Claude Code: kroky (nástroj a vstup),
text asistenta, výsledky nástrojů zkráceně a závěrečná zpráva.

Použití: python3 prubeh.py relace.jsonl PRUBEH.md
"""
import json
import sys

src, out = sys.argv[1], sys.argv[2]
lines = []
n = 0
for l in open(src, encoding='utf-8'):
    try:
        e = json.loads(l)
    except Exception:
        continue
    t = e.get('type')
    if t == 'system' and e.get('subtype') == 'init':
        lines.append(f"model: {e.get('model')}  nástroj: Claude Code {e.get('claude_code_version', '')}")
    elif t == 'assistant':
        for c in e['message'].get('content', []):
            if c.get('type') == 'text' and c['text'].strip():
                lines.append('TEXT: ' + c['text'].strip().replace('\n', ' ')[:400])
            elif c.get('type') == 'tool_use':
                n += 1
                inp = c.get('input', {})
                s = inp.get('command') or inp.get('file_path') or inp.get('pattern') or json.dumps(inp, ensure_ascii=False)
                lines.append(f"[{n}] {c['name']}: {str(s)[:200]}")
    elif t == 'user':
        content = e['message'].get('content')
        for c in content if isinstance(content, list) else []:
            if c.get('type') == 'tool_result':
                r = c.get('content')
                if isinstance(r, list):
                    r = ' '.join(x.get('text', '') for x in r if isinstance(x, dict))
                r = str(r).strip().replace('\n', ' ')
                if r:
                    lines.append('   → ' + r[:220])
    elif t == 'result':
        lines.append(f"\nVÝSLEDEK ({e.get('num_turns')} kroků, {round((e.get('duration_ms') or 0) / 1000)} s):\n" + (e.get('result') or ''))
with open(out, 'w', encoding='utf-8', newline='\n') as f:
    f.write('\n'.join(lines))
print(n, 'volání nástrojů')
