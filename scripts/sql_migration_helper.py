#!/usr/bin/env python3
"""
SQL Migration Helper for Joomla ContentBuilderNG
Helps identify and suggest QueryBuilder conversions for raw SQL strings
"""

import re
import sys
import argparse
from pathlib import Path
from typing import List, Dict, Tuple

class SQLMigrationHelper:
    """Analyze and suggest SQL query migrations to Joomla QueryBuilder pattern"""
    
    def __init__(self, workspace_root: str = '.'):
        self.workspace_root = Path(workspace_root)
        self.php_files = []
        self.queries = {}
        
    def find_php_files(self, target_file: str = None) -> List[Path]:
        """Find PHP files with potential raw SQL"""
        if target_file:
            return [self.workspace_root / f for f in [target_file] if (self.workspace_root / f).exists()]
        
        # Scan administrator/src and site/src for PHP files
        php_files = []
        for pattern in ['administrator/src/**/*.php', 'site/src/**/*.php', 'script.php']:
            php_files.extend(self.workspace_root.glob(pattern))
        return php_files
    
    def analyze_file(self, filepath: Path) -> Dict:
        """Analyze a PHP file for raw SQL patterns"""
        with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        # Find all setQuery patterns
        setquery_pattern = r'setQuery\s*\(\s*["\']'
        matches = list(re.finditer(setquery_pattern, content))
        
        queries = {
            'file': str(filepath.relative_to(self.workspace_root)),
            'total_setquery_calls': len(matches),
            'simple_selects': 0,
            'complex_queries': 0,
            'examples': []
        }
        
        for match in matches:
            # Extract the query
            start = match.start()
            # Find the matching quote
            quote_char = content[match.end() - 1]
            end = content.find(quote_char, match.end())
            
            if end > 0:
                query_string = content[match.end():end]
                query_upper = query_string.upper().strip()
                
                # Categorize
                if query_upper.startswith('SELECT'):
                    if '*' in query_string and 'WHERE' not in query_upper:
                        queries['simple_selects'] += 1
                    else:
                        queries['complex_queries'] += 1
                    
                    # Store first few examples
                    if len(queries['examples']) < 3:
                        line_num = content[:start].count('\n') + 1
                        queries['examples'].append({
                            'line': line_num,
                            'query': query_string[:100] + ('...' if len(query_string) > 100 else '')
                        })
        
        return queries
    
    def suggest_migration(self, query_string: str) -> str:
        """Suggest a QueryBuilder migration for a raw SQL query"""
        query_upper = query_string.upper().strip()
        
        # Simple SELECT *
        if query_upper.startswith('SELECT *'):
            # Extract table and where conditions
            from_match = re.search(r'FROM\s+(#__\w+|\w+)', query_upper)
            if from_match:
                table = from_match.group(1)
                return f"""$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('{table}'))
    ->where(...);  // Add WHERE conditions using ->where() chaining"""
        
        return "# Manual review required for this query"
    
    def report(self, files: List[Path] = None):
        """Generate analysis report"""
        if not files:
            files = self.find_php_files()
        
        total_files = 0
        total_queries = 0
        
        print("=== SQL Migration Analysis Report ===\n")
        
        for filepath in sorted(files):
            if not filepath.exists():
                print(f"File not found: {filepath}")
                continue
            
            analysis = self.analyze_file(filepath)
            total_count = analysis['total_setquery_calls']
            
            if total_count > 0:
                print(f"\n📄 {analysis['file']}")
                print(f"   Total setQuery() calls: {total_count}")
                print(f"   - Simple SELECTs: {analysis['simple_selects']}")
                print(f"   - Complex queries: {analysis['complex_queries']}")
                
                if analysis['examples']:
                    print("   Examples:")
                    for ex in analysis['examples']:
                        print(f"     Line {ex['line']}: {ex['query']}")
                
                total_files += 1
                total_queries += total_count
        
        print(f"\n=== Summary ===")
        print(f"Files with raw SQL: {total_files}")
        print(f"Total setQuery() calls: {total_queries}")
        print(f"\nEstimated migration effort: {total_queries * 5}-{total_queries * 15} minutes")
        print(f"Priority: Start with administrator/src/Service/ArticleService.php (29 queries)")

def main():
    parser = argparse.ArgumentParser(description='SQL Migration Helper for Joomla ContentBuilderNG')
    parser.add_argument('--report', action='store_true', help='Generate analysis report')
    parser.add_argument('--file', type=str, help='Analyze specific file')
    parser.add_argument('--suggest', type=str, help='Suggest migration for query')
    
    args = parser.parse_args()
    
    helper = SQLMigrationHelper()
    
    if args.report:
        files = [Path(args.file)] if args.file else None
        helper.report(files)
    elif args.suggest:
        print(helper.suggest_migration(args.suggest))
    else:
        helper.report()

if __name__ == '__main__':
    main()
