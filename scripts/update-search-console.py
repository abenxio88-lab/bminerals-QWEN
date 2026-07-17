#!/usr/bin/env python3
"""
Update all HTML files with search console functionality
"""
import os
import re
from pathlib import Path

PROJECT_ROOT = r"C:\Users\Lenovo\Documents\GitHub\bminerals-QWEN"

# Define main HTML files to update (excluding index.html which is already updated)
MAIN_HTML_FILES = [
    "products.html",
    "projects.html", 
    "our-mines.html",
    "logistics.html",
    "investors.html",
    "about.html",
    "blogs.html",
    "contact.html",
    "sustainability.html",
    "compliance.html",
    "terms.html",
    "privacy.html",
    "ethics.html",
    "404.html",
]

# Blog files
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

# Document files
DOCUMENT_FILES = [
    "documents/annual-report-2025.html",
    "documents/environmental-impact-assessment.html",
    "documents/esg-sustainability-report.html",
    "documents/hse-policy-performance.html",
    "documents/jorc-resource-statement.html",
    "documents/q3-2025-financial-results.html",
]

ALL_FILES = MAIN_HTML_FILES + BLOG_FILES + DOCUMENT_FILES

SEARCH_CONSOLE_CSS_LINK = '  <!-- Search Console CSS -->\n  <link rel="stylesheet" href="css/search-console.css">'

SEARCH_BUTTON_HTML = '''      <div class="navbar__search">
        <button class="navbar__search-btn" aria-label="Open search" title="Search (Ctrl+K)">
          🔍
        </button>
      </div>
'''

SEARCH_CONSOLE_MODAL = '''  <!-- Search Console Modal -->
  <div class="search-console" role="dialog" aria-labelledby="search-title">
    <div class="search-console__container">
      <div class="search-console__header">
        <span class="search-console__icon">🔍</span>
        <input 
          type="text" 
          class="search-console__input" 
          placeholder="Search products, pages, articles..." 
          autocomplete="off"
        >
        <button class="search-console__close" aria-label="Close search">✕</button>
      </div>
      <div class="search-console__results"></div>
    </div>
  </div>
'''

def update_file(filepath):
    """Update a single HTML file with search functionality"""
    if not os.path.exists(filepath):
        print(f"❌ File not found: {filepath}")
        return False
    
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Skip if already updated
    if 'search-console.css' in content:
        print(f"⊘ Already updated: {filepath}")
        return True
    
    # Add CSS link
    if 'cookies-popup.css' in content:
        content = content.replace(
            '  <link rel="stylesheet" href="css/cookies-popup.css">',
            '  <link rel="stylesheet" href="css/cookies-popup.css">\n\n' + SEARCH_CONSOLE_CSS_LINK
        )
    elif 'output.css' in content and '</head>' in content:
        # Fallback: add before closing head tag
        content = content.replace(
            '</head>',
            '\n  ' + SEARCH_CONSOLE_CSS_LINK + '\n</head>'
        )
    
    # Add search button before hamburger in navbar
    search_button_pattern = r'(\s+)<button class="navbar__hamburger"'
    if re.search(search_button_pattern, content):
        content = re.sub(
            search_button_pattern,
            f'\n{SEARCH_BUTTON_HTML}      <button class="navbar__hamburger"',
            content
        )
    
    # Add search console modal after </header>
    if '</header>' in content:
        # Find the closing header tag with comment
        if '<!-- @SECTION: HEADER END -->' in content:
            content = content.replace(
                '</header>\n  <!-- @SECTION: HEADER END -->',
                f'</header>\n\n{SEARCH_CONSOLE_MODAL}\n  <!-- @SECTION: HEADER END -->'
            )
        else:
            content = content.replace(
                '</header>',
                f'</header>\n\n{SEARCH_CONSOLE_MODAL}'
            )
    
    # Write back
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    
    print(f"✓ Updated: {filepath}")
    return True

# Main execution
if __name__ == '__main__':
    os.chdir(PROJECT_ROOT)
    print(f"🔍 Starting search console update in: {PROJECT_ROOT}\n")
    
    updated_count = 0
    failed_count = 0
    
    for html_file in ALL_FILES:
        filepath = os.path.join(PROJECT_ROOT, html_file)
        try:
            if update_file(filepath):
                updated_count += 1
            else:
                failed_count += 1
        except Exception as e:
            print(f"❌ Error updating {html_file}: {e}")
            failed_count += 1
    
    print(f"\n📊 Summary:")
    print(f"   Updated: {updated_count}")
    print(f"   Failed: {failed_count}")
    print(f"   Total: {len(ALL_FILES)}")
