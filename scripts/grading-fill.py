#!/usr/bin/env python3
"""Fill a copy of the class spreadsheet from a Chat AI Penilaian CSV export.

Students are matched by the NIM in their name, and groups by the group's name,
never by row order, so a missing submission cannot shift grades onto someone else. Columns are
matched by criterion name. Only approved grades are written. The original file is
not touched, and scores already in the copy need --overwrite to change.

    python3 scripts/grading-fill.py nilai.xlsx uts-3b.csv
    python3 scripts/grading-fill.py nilai-dinilai.xlsx uts-3c.csv --out nilai-dinilai-2.xlsx

Needs: pip install openpyxl
"""

import argparse
import csv
import re
import sys
from pathlib import Path

import openpyxl

NOT_CRITERIA = {"nama", "total", "skala 0-100", "catatan", "status"}


def normalise(heading):
    """'(30) Pelafalan ', 'Pelafalan (20%)' and 'pelafalan' are the same column."""
    text = re.sub(r"^\(\d+\)\s*|\s*\(\d+\s*%\)$", "", str(heading).strip())

    return re.sub(r"\s+", " ", text).lower()


def number(text):
    value = float(text)

    return int(value) if value.is_integer() else value


def read_export(path):
    with open(path, newline="", encoding="utf-8-sig") as handle:
        reader = csv.DictReader(handle)
        criteria = [h for h in (reader.fieldnames or []) if h.strip().lower() not in NOT_CRITERIA]

        return criteria, list(reader)


def locate(wb):
    """{NIM or group name: (worksheet, row)} and {worksheet title: {normalised heading: column}}."""
    rows = {}
    headings = {}

    for ws in wb.worksheets:
        for row in ws.iter_rows(max_row=30):
            found = {normalise(c.value): c.column for c in row if c.value is not None}
            key_column = next((column for heading, column in found.items() if heading.startswith(("nim", "kelompok"))), None)

            if key_column is None:
                continue

            headings[ws.title] = found

            for line in range(row[0].row + 1, ws.max_row + 1):
                key = ws.cell(line, key_column).value

                if key is not None:
                    rows[str(key).strip()] = (ws, line)

            break

    return rows, headings


def find_row(name, rows):
    """The row for this name: its NIM, or a group's whole name ('Group 1' is not 'Group 10')."""
    # Students name files their own way: the NIM may sit anywhere in the name.
    match = re.search(r"\d{6,}", name)

    if match and match.group() in rows:
        return rows[match.group()]

    for key, row in rows.items():
        if re.search(rf"(?<!\w){re.escape(key)}(?!\w)", name, re.IGNORECASE):
            return row

    return None


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("xlsx", type=Path)
    parser.add_argument("csv", type=Path)
    parser.add_argument("--out", type=Path)
    parser.add_argument("--overwrite", action="store_true", help="replace scores already in the sheet")
    args = parser.parse_args()

    out = args.out or args.xlsx.with_name(f"{args.xlsx.stem}-dinilai.xlsx")

    if out.resolve() == args.xlsx.resolve():
        print("File hasil harus berbeda dari file asli.", file=sys.stderr)

        return 2

    wb = openpyxl.load_workbook(args.xlsx)
    criteria, grades = read_export(args.csv)
    rows, headings = locate(wb)

    written = []
    pending = []
    unknown = []
    kept = []

    for grade in grades:
        name = grade.get("Nama", "").lstrip("'")
        found = find_row(name, rows)

        if found is None:
            unknown.append(name)
            continue

        if grade.get("Status", "").strip() != "disetujui":
            pending.append(name)
            continue

        ws, line = found

        for criterion in criteria:
            column = headings[ws.title].get(normalise(criterion.lstrip("'")))

            if column is None:
                # Stop before saving anything: a half-filled grade sheet is worse than none.
                print(f"Kolom kriteria '{criterion}' tidak ada di sheet {ws.title}. Samakan nama kriterianya.", file=sys.stderr)

                return 1

            cell = ws.cell(line, column)
            value = number(grade[criterion])

            if cell.value is not None and cell.value != value and not args.overwrite:
                kept.append(f"{name} ({criterion})")
                continue

            cell.value = value

        feedback = next((column for heading, column in headings[ws.title].items() if heading.startswith("umpan balik")), None)

        if feedback is not None and grade.get("Catatan", "").strip():
            ws.cell(line, feedback).value = grade["Catatan"].lstrip("'")

        written.append(name)

    wb.save(out)

    print(f"Terisi {len(written)} mahasiswa: {out}")

    for title, items in (
        ("Belum disetujui, dibiarkan kosong", pending),
        ("NIM atau kelompok tidak ada di file Excel", unknown),
        ("Nilai sudah terisi, tidak diubah (pakai --overwrite)", kept),
    ):
        if items:
            print(f"\n{title}:")
            print("\n".join(f"  {item}" for item in items))

    return 0


if __name__ == "__main__":
    sys.exit(main())
