#!/usr/bin/env python3
"""Screenshot every learnings-doc preview target into docs/shots/*.jpg
so the doc can be published as a static site (no localhost iframes).

Captures 1440x4800 with headless Chrome, downsamples to 960px wide JPEG.
"""

import subprocess, tempfile, shutil
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor

ROOT = Path(__file__).resolve().parent.parent
SHOTS = ROOT / "docs" / "shots"
SHOTS.mkdir(parents=True, exist_ok=True)

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

CITIES = [
    (91, "allentown"), (95, "altoona"), (165, "beaver-falls"), (92, "bethlehem"),
    (170, "bradford"), (164, "butler"), (126, "carlisle"), (125, "chambersburg"),
    (136, "coatesville"), (156, "columbia"), (122, "doylestown"), (171, "dubois"),
    (134, "east-stroudsburg"), (99, "easton"), (137, "ephrata"), (93, "erie"),
    (157, "gettysburg"), (161, "greensburg"), (158, "hanover"), (90, "harrisburg"),
    (132, "hazleton"), (128, "hershey"), (163, "indiana"), (162, "johnstown"),
    (102, "king-of-prussia"), (87, "lancaster"), (129, "lebanon"), (130, "lewisburg"),
    (155, "lititz"), (167, "meadville"), (127, "mechanicsburg"), (121, "media"),
    (166, "new-castle"), (101, "norristown"), (168, "oil-city"), (135, "phoenixville"),
    (94, "pittsburgh"), (100, "pottstown"), (123, "quakertown"), (89, "reading"),
    (124, "scranton"), (131, "selinsgrove"), (159, "shippensburg"), (96, "state-college"),
    (133, "stroudsburg"), (169, "warren"), (160, "waynesboro"), (103, "west-chester"),
    (98, "wilkes-barre"), (97, "williamsport"), (88, "york"),
]

DESIGNS = ["bethlehem", "pittsburgh", "erie", "lancaster", "restaurant", "plumbing",
           "cpa", "auto", "landscaping", "law", "dental", "roofing", "styleguide"]

TARGETS = []
for slug in DESIGNS:
    TARGETS.append((f"design-{slug}", f"file://{ROOT}/tmp-{slug}-design.html", 15))
for pid, slug in CITIES:
    TARGETS.append((f"page-{slug}", f"http://127.0.0.1:8181/?page_id={pid}", 15))
TARGETS.append(("builder-wpcom", "https://terry1055e91d46-etfdr.wordpress.com/", 25))
# Playground boots a whole WP in-browser; give it a long budget
TARGETS.append(("builder-seedprod", "https://playground.wordpress.net/?login=no&mode=seamless#eyIkc2NoZW1hIjoiaHR0cHM6Ly9wbGF5Z3JvdW5kLndvcmRwcmVzcy5uZXQvYmx1ZXByaW50LXNjaGVtYS5qc29uIiwibGFuZGluZ1BhZ2UiOiIvIiwicHJlZmVycmVkVmVyc2lvbnMiOnsicGhwIjoiOC4zIiwid3AiOiJsYXRlc3QifSwiZmVhdHVyZXMiOnsibmV0d29ya2luZyI6dHJ1ZX0sImV4dHJhTGlicmFyaWVzIjpbIndwLWNsaSJdLCJzdGVwcyI6W3sic3RlcCI6ImxvZ2luIiwidXNlcm5hbWUiOiJzZWVkcHJvZCJ9LHsic3RlcCI6Imluc3RhbGxQbHVnaW4iLCJwbHVnaW5EYXRhIjp7InJlc291cmNlIjoidXJsIiwidXJsIjoiaHR0cHM6Ly9wdWItNDMyMjk0Y2I2MjM3NDE4ZTg4NjkwZjJkYTMwZTgxZTAucjIuZGV2L3NlZWRwcm9kLWNvbWluZy1zb29uLXByby01LTYuMTguMTYuemlwIn0sIm9wdGlvbnMiOnsiYWN0aXZhdGUiOnRydWV9fSx7InN0ZXAiOiJzZXRTaXRlT3B0aW9ucyIsIm9wdGlvbnMiOnsiYmxvZ25hbWUiOiJLZXlzdG9uZSBDb21mb3J0IENvLiIsInRpbWV6b25lX3N0cmluZyI6IkFtZXJpY2EvTmV3X1lvcmsifX0seyJzdGVwIjoid3AtY2xpIiwiY29tbWFuZCI6IndwIHNlZWRwcm9kX2ltcG9ydF90aGVtZV9mcm9tX3VybCBodHRwczovL3B1Yi00MzIyOTRjYjYyMzc0MThlODg2OTBmMmRhMzBlODFlMC5yMi5kZXYvYWkvZXhwb3J0X3BhZ2UtMjc3MDQuemlwIn0seyJzdGVwIjoid3AtY2xpIiwiY29tbWFuZCI6IndwIHNlZWRwcm9kX2VuYWJsZV90aGVtZSB0cnVlIn1dfQ", 90))


def shoot(target):
    name, url, wait = target
    out = SHOTS / f"{name}.jpg"
    if out.exists():
        return f"skip {name}"
    with tempfile.TemporaryDirectory() as profile:
        png = Path(profile) / "shot.png"
        # No --timeout / --virtual-time-budget: both make headless hang here.
        # Capture fires on the load event; the OS-level timeout is the backstop.
        cmd = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars",
               f"--user-data-dir={profile}", f"--screenshot={png}",
               "--window-size=1440,4800", url]
        try:
            subprocess.run(cmd, capture_output=True, timeout=wait + 60)
            if not png.exists():
                return f"FAIL {name}: no screenshot produced"
            subprocess.run(["sips", "--resampleWidth", "960",
                            "-s", "format", "jpeg", "-s", "formatOptions", "78",
                            str(png), "--out", str(out)], capture_output=True, timeout=60)
            kb = out.stat().st_size // 1024
            return f"ok {name} ({kb}KB)"
        except subprocess.TimeoutExpired:
            return f"FAIL {name}: timeout"


with ThreadPoolExecutor(max_workers=1) as pool:
    for result in pool.map(shoot, TARGETS):
        print(result, flush=True)

n = len(list(SHOTS.glob("*.jpg")))
print(f"\n{n} shots in {SHOTS}")
