#!/usr/bin/env python3
"""Outil de contrôle des traductions Joomla pour com_contentbuilderng.

Usage:
  python scripts/l10n_duplicate_check.py --report
  python scripts/l10n_duplicate_check.py --report --merge-suggestion

Le script détecte les clefs qui partagent la même valeur dans les fichiers .ini
et propose un regroupement des clefs identiques (même valeur) dans un rapport.
"""

import argparse
import glob
import os


def parse_inis(paths):
    entries = []
    for path in sorted(paths):
        if not path.endswith('.ini'):
            continue
        with open(path, 'r', encoding='utf-8') as f:
            for line_number, line in enumerate(f, 1):
                stripped = line.strip()
                if not stripped or stripped.startswith('#') or stripped.startswith(';'):
                    continue
                if '=' not in stripped:
                    continue
                key, value = stripped.split('=', 1)
                key = key.strip()
                value = value.strip().strip('"')
                entries.append((path, key, value, line_number))
    return entries


def find_duplicates(entries):
    value_map = {}
    for path, key, value, line in entries:
        value_map.setdefault(value, []).append((path, key, line))
    return {value: arr for value, arr in value_map.items() if len(arr) > 1}


def print_report(duplicates, max_items=50):
    print(f"Found {len(duplicates)} duplicate translation values")
    count = 0
    for value, items in sorted(duplicates.items(), key=lambda kv: (-len(kv[1]), kv[0])):
        count += 1
        if count > max_items:
            break
        print('\nVALUE:', value)
        for path, key, line in items:
            print('  ', path, line, key)


def suggest_merges(duplicates):
    suggestions = []
    for value, items in duplicates.items():
        # On propose unification uniquement si toutes les clefs diffèrent.
        keys = sorted({key for _, key, _ in items})
        if len(keys) > 1:
            canon = keys[0]
            others = keys[1:]
            suggestions.append((value, canon, others, items))
    return suggestions


def print_merge_suggestions(suggestions, max_items=20):
    print(f"\nMerge suggestions for {len(suggestions)} duplicate values (same text, different keys)")
    for idx, (value, canon, others, items) in enumerate(suggestions[:max_items], 1):
        print(f"\n{idx}. VALUE: {value}")
        print(f"   canonical key: {canon}")
        print(f"   keys to replace: {', '.join(others)}")
        print("   occurrences:")
        for path, key, line in items:
            print('     ', path, line, key)


def main():
    parser = argparse.ArgumentParser(description='L10N duplicate value checker for ContentBuilderNG')
    parser.add_argument('--report', action='store_true', help='Show duplicate value report')
    parser.add_argument('--merge-suggestion', action='store_true', help='Show recommended canonicalization')
    parser.add_argument('--max', type=int, default=50, help='Report limit')
    args = parser.parse_args()

    base = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
    inis = glob.glob(os.path.join(base, 'administrator', 'languages', '**', '*.ini'), recursive=True)
    inis += glob.glob(os.path.join(base, 'site', 'languages', '**', '*.ini'), recursive=True)

    entries = parse_inis(inis)
    duplicates = find_duplicates(entries)

    if args.report:
        print_report(duplicates, max_items=args.max)
    if args.merge_suggestion:
        suggestions = suggest_merges(duplicates)
        print_merge_suggestions(suggestions, max_items=args.max)
    if not args.report and not args.merge_suggestion:
        parser.print_help()


if __name__ == '__main__':
    main()
