#!/usr/bin/env python3
"""Download students' videos listed in a class spreadsheet, ready for Chat AI Penilaian.

Runs on the lecturer's computer, because hosts are routinely blocked by YouTube.
Every sheet with NIM, Nama and Link columns is read. Files land in
<out>/<sheet>/<NIM> - <Nama>.mp4, so the app names each student from the file.
Finished videos are skipped on the next run; failures go to <out>/gagal.csv.

    python3 scripts/grading-download.py nilai.xlsx
    python3 scripts/grading-download.py nilai.xlsx --sheet 3B --dry-run

Needs: pip install openpyxl, plus yt-dlp and ffmpeg on PATH.
"""

import argparse
import csv
import re
import shutil
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path

import openpyxl

# H.264 first: every browser can read its frames, and HEVC often cannot.
FORMAT = (
    "bv*[height<=720][vcodec^=avc1]+ba[ext=m4a]"
    "/b[height<=720][vcodec^=avc1]"
    "/bv*[height<=720]+ba"
    "/b[height<=720]/b"
)
TIMEOUT_SECONDS = 1800


@dataclass
class Student:
    sheet: str
    nim: str
    name: str
    link: str


def header_columns(ws):
    """Find the header row: (row number, {heading: column}), or None for other sheets."""
    for row in ws.iter_rows(max_row=30):
        headings = {str(c.value).strip().lower(): c.column for c in row if c.value is not None}
        columns = {}

        for heading, column in headings.items():
            for key in ("nim", "nama", "link"):
                if heading.startswith(key):
                    columns.setdefault(key, column)

        if len(columns) == 3:
            return row[0].row, columns

    return None


def read_students(path, sheets=None):
    wb = openpyxl.load_workbook(path)

    for ws in wb.worksheets:
        if sheets and ws.title not in sheets:
            continue

        found = header_columns(ws)

        if found is None:
            continue

        header, columns = found

        for row in range(header + 1, ws.max_row + 1):
            nim = ws.cell(row, columns["nim"]).value

            if nim is None:
                continue

            cell = ws.cell(row, columns["link"])
            # Some students paste a titled link: the text is not the address.
            link = cell.hyperlink.target if cell.hyperlink else (cell.value or "")

            yield Student(ws.title, str(nim).strip(), str(ws.cell(row, columns["nama"]).value or "").strip(), str(link).strip())


def file_name(student):
    name = re.sub(r"\s+", " ", re.sub(r'[/\\:*?"<>|%]', "-", student.name)).strip()

    return f"{student.nim} - {name}.mp4"


def download(student, target):
    result = subprocess.run(
        [
            "yt-dlp", "--no-playlist", "--no-warnings", "--quiet",
            "-f", FORMAT, "--merge-output-format", "mp4",
            "-o", str(target),
            # After --, a spreadsheet cell can never be read as an option.
            "--", student.link,
        ],
        capture_output=True, text=True, timeout=TIMEOUT_SECONDS,
    )

    if result.returncode == 0 and target.exists():
        return None

    lines = (result.stderr or "").strip().splitlines()

    return lines[-1] if lines else f"yt-dlp berhenti dengan kode {result.returncode}"


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("xlsx", type=Path)
    parser.add_argument("--out", type=Path, help="default: a folder next to the spreadsheet, outside any repository")
    parser.add_argument("--sheet", action="append", help="only this sheet; repeat for more")
    parser.add_argument("--dry-run", action="store_true", help="list what would be downloaded")
    args = parser.parse_args()

    out = args.out or args.xlsx.with_name(f"{args.xlsx.stem}-video")
    students = list(read_students(args.xlsx, args.sheet))

    if not students:
        sys.exit("Tidak ada sheet dengan kolom NIM, Nama, dan Link.")

    if not args.dry_run and shutil.which("yt-dlp") is None:
        sys.exit("yt-dlp tidak ditemukan. Pasang dulu: brew install yt-dlp ffmpeg")

    failures = []
    report = out / "gagal.csv"

    for number, student in enumerate(students, start=1):
        target = out / student.sheet / file_name(student)
        label = f"[{number}/{len(students)}] {student.sheet} {target.name}"

        if not re.match(r"https?://", student.link):
            failures.append((student, "Link tidak valid"))
            print(f"{label}: link tidak valid")
            continue

        if target.exists():
            print(f"{label}: sudah ada")
            continue

        if args.dry_run:
            print(f"{label}: {student.link}")
            continue

        target.parent.mkdir(parents=True, exist_ok=True)
        print(label, flush=True)

        try:
            error = download(student, target)
        except subprocess.TimeoutExpired:
            error = "Waktu unduh habis"

        if error:
            failures.append((student, error))
            print(f"  gagal: {error}")

    if args.dry_run:
        return 0

    if failures:
        out.mkdir(parents=True, exist_ok=True)

        with open(report, "w", newline="", encoding="utf-8-sig") as handle:
            writer = csv.writer(handle)
            writer.writerow(["Sheet", "NIM", "Nama", "Link", "Alasan"])
            writer.writerows((s.sheet, s.nim, s.name, s.link, reason) for s, reason in failures)

        print(f"\n{len(failures)} dari {len(students)} gagal. Daftarnya: {report}")

        return 1

    report.unlink(missing_ok=True)
    print(f"\nSelesai: {len(students)} video di {out}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
