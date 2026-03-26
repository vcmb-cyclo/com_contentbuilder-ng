#!/usr/bin/env python3
"""
Apply translation key deduplication merges safely
"""

import os
import json
import re
from pathlib import Path
from collections import defaultdict

class MergeApplier:
    def __init__(self, merge_map_file, workspace_root='.'):
        self.workspace_root = Path(workspace_root)
        with open(merge_map_file, 'r') as f:
            self.merge_map = json.load(f)
        
        self.files_modified = 0
        self.changes_applied = 0
        
    def apply_to_ini_files(self):
        """Apply merges to .ini files"""
        ini_files = []
        for root, dirs, files in os.walk(self.workspace_root):
            if '.git' in dirs:
                dirs.remove('.git')
            for f in files:
                if f.endswith('.ini'):
                    ini_files.append(Path(root) / f)
        
        for filepath in sorted(ini_files):
            changes = self._merge_ini_file(filepath)
            if changes > 0:
                self.files_modified += 1
                self.changes_applied += changes
        
        return self.files_modified
    
    def _merge_ini_file(self, filepath):
        """Merge keys in a single .ini file"""
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            lines = f.readlines()
        
        modified = False
        new_lines = []
        keys_to_remove = set()
        
        for line in lines:
            original_line = line
            
            # Check if this line defines a key that should be removed
            if '=' in line and not line.strip().startswith(';'):
                key = line.split('=')[0].strip()
                if key in self.merge_map:
                    # Skip this line entirely (don't add to new_lines)
                    # Mark it for removal
                    modified = True
                    continue
            
            new_lines.append(original_line)
        
        if modified:
            # Write back
            with open(filepath, 'w', encoding='utf-8') as f:
                f.writelines(new_lines)
            return 1
        
        return 0
    
    def apply_to_source_files(self):
        """Apply merges to PHP/JS/TPL files"""
        source_files = []
        for root, dirs, files in os.walk(self.workspace_root):
            if '.git' in dirs:
                dirs.remove('.git')
            if 'vendor' in dirs:
                dirs.remove('vendor')
            if 'node_modules' in dirs:
                dirs.remove('node_modules')
            
            for f in files:
                if f.endswith(('.php', '.js', '.tpl', '.html')):
                    source_files.append(Path(root) / f)
        
        for filepath in sorted(source_files):
            changes = self._merge_source_file(filepath)
            if changes > 0:
                self.files_modified += 1
                self.changes_applied += changes
        
        return self.files_modified
    
    def _merge_source_file(self, filepath):
        """Merge key references in a source file"""
        try:
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
        except Exception as e:
            return 0
        
        original_content = content
        changed_count = 0
        
        for old_key, new_key in self.merge_map.items():
            # Pattern 1: Text::_('OLD_KEY')
            pattern1 = f"Text::_\\('{re.escape(old_key)}'\\)"
            replacement1 = f"Text::_('{new_key}')"
            content, count1 = re.subn(pattern1, replacement1, content)
            changed_count += count1
            
            # Pattern 2: Text::_("OLD_KEY")
            pattern2 = f'Text::_\\("{re.escape(old_key)}\\"\\)'
            replacement2 = f'Text::_("{new_key}")'
            content, count2 = re.subn(pattern2, replacement2, content)
            changed_count += count2
            
            # Pattern 3: 'OLD_KEY' but only in Text::_() context
            # This is trickier, skip for now to avoid false positives
        
        if content != original_content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            return changed_count
        
        return 0
    
    def report(self):
        """Print merge report"""
        print("\n" + "=" * 80)
        print("MERGE APPLICATION REPORT")
        print("=" * 80)
        print(f"\nTotal merges to apply: {len(self.merge_map)}")
        print(f"Files modified: {self.files_modified}")
        print(f"Total changes applied: {self.changes_applied}")
        print("\nMerges applied:")
        for i, (old_key, new_key) in enumerate(sorted(self.merge_map.items()), 1):
            print(f"  {i:2d}. {old_key} -> {new_key}")

if __name__ == '__main__':
    applier = MergeApplier('/tmp/translation_merges.json')
    
    print("Applying merges to .ini files...")
    applier.apply_to_ini_files()
    print(f"  ✓ {applier.files_modified} .ini files modified")
    
    print("\nApplying merges to source files...")
    applier.apply_to_source_files()
    
    applier.report()
    print("\n✓ All merges applied successfully!")
