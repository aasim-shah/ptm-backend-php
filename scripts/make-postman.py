#!/usr/bin/env python3
"""Builds docs/postman.json from the route export.

    php scripts/export-routes.php > build/routes.json
    python3 scripts/make-postman.py build/routes.json docs/postman.json
"""
import json
import re
import sys
import uuid

routes = json.load(open(sys.argv[1]))
out = sys.argv[2]

API_GROUPS = [
    ("01 Auth & Profile", r"^/api/(auth/|logout|forgot|reset/password|send/pincode|verify/pincode|forgot-password|verify-otp|set-password|change-password|user/schools)"),
    ("02 Student App", r"^/api/student/(login|forgot-password|subjects|class-subjects|select-subjects|parent-details|timetable|lessons|lesson-topics|assignments|submit-|delete-assignment|attendance|announcements|get-|exam-marks)"),
    ("03 Parent App", r"^/api/parent/"),
    ("04 Teacher App", r"^/api/teacher/"),
    ("05 Schools, Classes & Students (shared)", r"^/api/(add/school|get/schools|get/schools-details|get/teacher/schools|get/countries|states/|mediums|sections|subjects|student/assign|student/remove|students|get/teachers|get/parents|search/user|student-attendance)"),
    ("06 Meetings & Video Calls", r"^/api/(add/meetings|get/meetings|get/status/meetings|search/meetings|update/meetings|join/call|left/call|channel/token)"),
    ("07 Chat & Notifications", r"^/api/(chat/|notifications)"),
    ("08 Subscriptions (PayPal)", r"^/api/(plan|plans|subscription|active/subscription)"),
    ("09 General / Public", r"^/api/(holidays|quarters|sliders|current-session-year|settings)"),
]
FILE_RE = re.compile(r"^(image|file|files|attachment|logo|photo|document|avatar)$", re.I)


def auth_of(mw):
    if "auth:sanctum" in mw:
        return "bearer"
    if "auth" in mw or "Role" in mw:
        return "session"
    return "none"


def item(r):
    uri = r["uri"]
    verbs = r["methods"]
    method = "POST" if len(verbs) > 2 else verbs[0]
    path_vars = re.findall(r"\{(\w+)\??\}", uri)
    url_path = re.sub(r"\{(\w+)\??\}", lambda m: "{{" + m.group(1) + "}}", uri)
    mw = r["middleware"]
    params = [p for p in r["params"] if p not in path_vars]
    rules = r["rules"] or {}
    if "checkChild" in mw and "child_id" not in params:
        params = ["child_id"] + params
    if "checkStudent" in mw and "student_id" not in params:
        params = ["student_id"] + params

    a = auth_of(mw)
    desc = [f"**Handler:** `{r['action'].split(chr(92))[-1]}`" + (f" — `{r['file']}:{r['line']}`" if r["file"] else "")]
    desc.append("**Auth:** " + {"bearer": "Bearer token (Sanctum)", "session": "Web session + CSRF (admin panel)", "none": "Public"}[a])
    roles = [m.split(":", 1)[1] for m in mw if m.startswith(("userType:", "role:"))]
    if roles:
        desc.append("**Allowed users:** " + ", ".join(roles))
    if len(verbs) > 2:
        desc.append("**Verb:** route accepts any HTTP method; POST used here.")
    desc.append("**Middleware:** " + ", ".join(mw))
    if rules:
        desc.append("**Validation:**\n" + "\n".join(f"- `{k}`: {v}" for k, v in sorted(rules.items())))

    fields = []
    for p in params:
        rule = rules.get(p, "")
        is_file = bool(FILE_RE.match(p))
        f = {"key": p, "description": rule or "read by the handler",
             "disabled": "required" not in rule and p not in ("child_id", "student_id"),
             "type": "file" if is_file else "text"}
        if is_file:
            f["src"] = []
        else:
            f["value"] = "{{" + p + "}}" if p in ("child_id", "student_id") else ""
        fields.append(f)

    req = {"method": method, "header": [{"key": "Accept", "value": "application/json"}],
           "url": {"raw": "{{base_url}}" + url_path, "host": ["{{base_url}}"],
                   "path": [s for s in url_path.strip("/").split("/") if s]},
           "description": "\n\n".join(desc)}
    if path_vars:
        req["url"]["variable"] = [{"key": v, "value": "1"} for v in path_vars]
    if a == "none":
        req["auth"] = {"type": "noauth"}
    if method in ("GET", "DELETE"):
        if fields:
            req["url"]["query"] = [{"key": f["key"], "value": f.get("value", ""), "description": f["description"],
                                    "disabled": f["disabled"]} for f in fields]
            enabled = [f for f in fields if not f["disabled"]]
            if enabled:
                req["url"]["raw"] += "?" + "&".join(f["key"] + "=" + f.get("value", "") for f in enabled)
    else:
        req["body"] = {"mode": "formdata", "formdata": fields}
    return {"name": f"{'ANY' if len(verbs) > 2 else method} {uri}", "request": req, "response": []}


def folder(name, items, desc=""):
    return {"name": name, "description": desc, "item": items}


api = [r for r in routes if r["uri"].startswith("/api/")]
web = [r for r in routes if not r["uri"].startswith("/api/")]

groups = {g: [] for g, _ in API_GROUPS}
other = []
for r in sorted(api, key=lambda x: x["uri"]):
    for g, pat in API_GROUPS:
        if re.search(pat, r["uri"]):
            groups[g].append(item(r))
            break
    else:
        other.append(item(r))
api_folders = [folder(g, its) for g, its in groups.items() if its]
if other:
    api_folders.append(folder("10 Other", other))

wgroups = {}
for r in sorted(web, key=lambda x: x["uri"]):
    seg = r["uri"].strip("/").split("/")[0] or "(root)"
    wgroups.setdefault(seg, []).append(item(r))
web_folder = folder("Admin Panel (web routes, session auth)", [folder(k, v) for k, v in sorted(wgroups.items())],
                    "Blade admin panel routes (Super Admin / Principal). They use session cookies + CSRF, not Bearer "
                    "tokens: GET {{base_url}}/login, send the XSRF-TOKEN cookie value as header X-XSRF-TOKEN, then "
                    "POST /login-web.")

tests = [
    "const code = pm.response.code;",
    "pm.test('No server error (status < 500)', () => pm.expect(code).to.be.below(500));",
    "pm.test('Route exists (not 404)', () => pm.expect(code).to.not.eql(404));",
    "if (/\\/(auth\\/login|login)$/.test(pm.request.url.getPath())) {",
    "  try { const j = pm.response.json(); const t = j.token || (j.data && j.data.token); if (t) pm.collectionVariables.set('token', t); } catch (e) {}",
    "}",
]

coll = {
    "info": {
        "_postman_id": str(uuid.uuid4()),
        "name": "School System (Parent Teacher Mobile) — All Routes",
        "description": (
            "Generated from Laravel's registered routes (scripts/export-routes.php + scripts/make-postman.py).\n\n"
            "Each request lists its handler file:line, auth type, allowed user types and validation rules.\n\n"
            "Usage: set `base_url`, run `01 Auth & Profile / ANY /api/auth/login` (the test script stores `token`), "
            "then use the Collection Runner. Collection tests flag any 5xx or 404.\n\n"
            "Role rules: /api/teacher/* needs a teacher or principal token, /api/parent/* a parent token, "
            "/api/student/* a student token."
        ),
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
    },
    "auth": {"type": "bearer", "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}]},
    "event": [{"listen": "test", "script": {"type": "text/javascript", "exec": tests}}],
    "variable": [
        {"key": "base_url", "value": "http://127.0.0.1:8000"},
        {"key": "token", "value": ""},
        {"key": "child_id", "value": ""},
        {"key": "student_id", "value": ""},
    ],
    "item": [folder("Mobile API (/api, Bearer token)", api_folders), web_folder],
}
json.dump(coll, open(out, "w"), indent=2, ensure_ascii=False)


def count(items):
    return sum(count(i["item"]) if "item" in i else 1 for i in items)


print("requests:", count(coll["item"]), "| api:", count(api_folders), "| web:", count(web_folder["item"]))
