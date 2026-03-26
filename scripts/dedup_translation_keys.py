#!/usr/bin/env python3
"""
Safe translation key deduplication for Joomla ContentBuilderNG
- Finds duplicate translation VALUES (different keys, same text)
- Selects canonical key (most semantic, not alphabetical)
- Applies merges safely without creating malformed keys
"""

import os
import re
import json
from collections import defaultdict
from pathlib import Path

class TranslationDeduplicator:
    def __init__(self, workspace_root='.'):
        self.workspace_root = Path(workspace_root)
        self.all_keys = {}  # key -> value
        self.key_locations = defaultdict(list)  # key -> [(file, line_num)]
        self.duplicates = {}  # canonical_key -> [old_key1, old_key2, ...]
        
    def scan_ini_files(self):
        """Scan all .ini files and collect keys"""
        ini_files = []
        for root, dirs, files in os.walk(self.workspace_root):
            if '.git' in dirs:
                dirs.remove('.git')
            for f in files:
                if f.endswith('.ini'):
                    ini_files.append(Path(root) / f)
        
        for filepath in sorted(ini_files):
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                for i, line in enumerate(f, 1):
                    line = line.strip()
                    if '=' in line and not line.startswith(';'):
                        try:
                            key, value = line.split('=', 1)
                            key = key.strip()
                            value = value.strip().strip('"')
                            
                            # Skip malformed keys (with unexpected dots)
                            if '.' in key and not key.startswith('_'):
                                continue
                            
                            if key:
                                self.all_keys[key] = value
                                self.key_locations[key].append((str(filepath.relative_to(self.workspace_root)), i))
                        except:
                            pass
    
    def find_duplicate_values(self):
        """Find keys with duplicate VALUES"""
        value_to_keys = defaultdict(list)
        
        for key, value in self.all_keys.items():
            if value and len(value) > 2:
                value_to_keys[value].append(key)
        
        return {v: keys for v, keys in value_to_keys.items() if len(keys) > 1}
    
    def select_canonical_key(self, duplicate_keys, value):
        """Select the best canonical key from duplicates"""
        # Preference order (most semantic = best)
        preferences = [
            lambda k: k.count('COM_CONTENTBUILDERNG') > 0,  # Prefer full component name
            lambda k: not k.endswith('_BUTTON'),  # Avoid generic _BUTTON suffix
            lambda k: not k.endswith('_LABEL'),   # Avoid generic _LABEL suffix
            lambda k: not k.startswith('PUBLISHED'),  # Avoid bare PUBLISHED
            lambda k: len(k) < 60,  # Prefer shorter, clearer names
        ]
        
        scored_keys = [(k, sum(1 for p in preferences if p(k))) for k in duplicate_keys]
        scored_keys.sort(key=lambda x: (-x[1], x[0]))  # Sort by score (descending), then alphabetically
        
        return scored_keys[0][0]
    
    def identify_merges(self):
        """Identify all merge candidates"""
        duplicates_by_value = self.find_duplicate_values()
        
        merges = {}  # canonical -> [old_key, ...]
        for value, keys in duplicates_by_value.items():
            canonical = self.select_canonical_key(keys, value)
            old_keys = [k for k in keys if k != canonical]
            if old_keys:
                merges[canonical] = old_keys
        
        return merges
    
    def report(self):
        """Print deduplication report"""
        self.scan_ini_files()
        merges = self.identify_merges()
        
        print("=" * 80)
        print("TRANSLATION KEY DEDUPLICATION REPORT")
        print("=" * 80)
        print(f"\nTotal keys scanned: {len(self.all_keys)}")
        print(f"Duplicate values found: {len(merges)}")
        print(f"Keys to be merged: {sum(len(v) for v in merges.values())}\n")
        
        print("MERGE CANDIDATES:\n")
        for i, (canonical, old_keys) in enumerate(sorted(merges.items()), 1):
            value = self.all_keys[canonical]
            print(f"{i}. VALUE: {value[:50]}{'...' if len(value) > 50 else ''}")
            print(f"   KEEP:   {canonical}")
            print(f"   REPLACE: {old_keys}")
            print()
        
        return merges
    
    def generate_merge_map(self):
        """Generate merge map for safe application"""
        self.scan_ini_files()
        merges = self.identify_merges()
        
        merge_map = {}  # old_key -> canonical_key
        for canonical, old_keys in merges.items():
            for old_key in old_keys:
                merge_map[old_key] = canonical
        
        return merge_map

if __name__ == '__main__':
    dedup = TranslationDeduplicator()
    merges = dedup.report()
    
    # Save merge map
    merge_map = dedup.generate_merge_map()
    with open('/tmp/translation_merges.json', 'w') as f:
        json.dump(merge_map, f, indent=2)
    print(f"\n✓ Merge map saved to /tmp/translation_merges.json")
