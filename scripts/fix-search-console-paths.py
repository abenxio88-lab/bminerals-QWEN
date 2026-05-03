#!/usr/bin/env python3
"""
Fix CSS paths for blog and document pages
"""
import os

PROJECT_ROOT = r"C:\Users\Lenovo\Documents\GitHub\bminerals-QWEN"

# Files that need path corrections
BLOG_FILES = [
    "blog/ai-automation-deep-pit-mining.html",
    "blog/barite-modern-drilling.html",
    "blog/chromite-high-grade-alloys.html",
    "blog/copper-electric-revolution.html",
    "blog/empowering-local-communities-mining.html",
    "blog/land-reclamation-life-after-mining.html",
    "blog/pakistan-global-mineral-supply-chain.html",
    "blog/streamlining-exports-gwadar-port.html",
    "blog/sustainable-mining-balochistan.html",
    "blog/vision-zero-workplace-accidents.html",
]

DOCUMENT_FILES = [
    "documents/annual-report-2025.html",
    "documents/environmental-impact-assessment.html",
    "documents/esg-sustainability-report.html",
    "documents/hse-policy-performance.html",
    "documents/jorc-resource-statement.html",
    "documents/q3-2025-financial-results.html",
]

def fix_paths(filepath):
    """Fix CSS paths for subdirectory files"""
    if not os.path.exists(filepath):
        print(f"❌ File not found: {filepath}")
        return False
    
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace the incorrect path with the correct one
    if 'href="css/search-console.css"' in content:
        content = content.replace(
            'href="css/search-console.css"',
            'href="../css/search-console.css"'
        )
        
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"✓ Fixed: {filepath}")
        return True
    else:
        print(f"⊘ No fix needed: {filepath}")
        return False

# Main execution
if __name__ == '__main__':
    os.chdir(PROJECT_ROOT)
    print(f"🔧 Fixing CSS paths in: {PROJECT_ROOT}\n")
    
    all_files = BLOG_FILES + DOCUMENT_FILES
    fixed_count = 0
    
    for html_file in all_files:
        filepath = os.path.join(PROJECT_ROOT, html_file)
        try:
            if fix_paths(filepath):
                fixed_count += 1
        except Exception as e:
            print(f"❌ Error fixing {html_file}: {e}")
    
    print(f"\n📊 Summary:")
    print(f"   Fixed: {fixed_count}")
    print(f"   Total: {len(all_files)}")
