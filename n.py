#!/usr/bin/env python3
# Fix php/config.php — remove the entire broken BASE_PATH injection block
# that has literal backslash-dollar (\$_h) causing parse error on line 6

path = '/var/www/html/safari-zangu/php/config.php'

with open(path, 'rb') as f:
    raw = f.read()

# Decode, removing any bad bytes
content = raw.decode('utf-8', errors='replace')

# Remove the entire broken injected block (lines 2-8 in the file)
# It starts with the duplicate BASE_PATH check and contains \$_h
BROKEN_BLOCK_1 = """if (!defined('BASE_PATH')) { require_once __DIR__ . '/../config/app.php'; }
// Bootstrap the new MVC layer
// Ensure BASE_PATH is available when loaded standalone
if (!defined('BASE_PATH')) {
    $_h = \\$_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_PATH', (str_contains(\\$_h,'localhost')||str_contains(\\$_h,'127.0.0.1')) ? '/safari-zangu' : '');
}
"""

BROKEN_BLOCK_2 = """if (!defined('BASE_PATH')) { require_once __DIR__ . '/../config/app.php'; }
// Bootstrap the new MVC layer
// Ensure BASE_PATH is available when loaded standalone
if (!defined('BASE_PATH')) {
    \$_h = \$_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_PATH', (str_contains(\$_h,'localhost')||(str_contains(\$_h,'127.0.0.1')) ? '/safari-zangu' : '');
}
"""

# Try exact match first
if BROKEN_BLOCK_1 in content:
    content = content.replace(BROKEN_BLOCK_1, '')
    print('Removed broken block (variant 1)')
elif BROKEN_BLOCK_2 in content:
    content = content.replace(BROKEN_BLOCK_2, '')
    print('Removed broken block (variant 2)')
else:
    # Nuclear option: strip everything from line 1 up to the real content
    # Find the first line that is NOT part of the injected garbage
    lines = content.split('\n')
    clean_start = 0
    for i, line in enumerate(lines):
        stripped = line.strip()
        # Skip the broken injected lines — they contain \$ or are the duplicate BASE_PATH block
        if '\\$' in line:
            clean_start = i + 1
            print(f'  Removing broken line {i+1}: {repr(line)[:80]}')
        elif stripped.startswith("if (!defined('BASE_PATH')) { require_once") :
            clean_start = i + 1
            print(f'  Removing duplicate require line {i+1}')
        elif stripped == "// Bootstrap the new MVC layer" and i < 10:
            clean_start = i + 1
            print(f'  Removing comment line {i+1}')
        elif stripped == "// Ensure BASE_PATH is available when loaded standalone":
            clean_start = i + 1
        elif stripped.startswith("if (!defined('BASE_PATH')) {") and i < 10:
            # Find the closing brace
            j = i
            while j < len(lines) and lines[j].strip() != '}':
                j += 1
            clean_start = j + 1
            print(f'  Removing injected BASE_PATH block lines {i+1}-{j+1}')
            break

    if clean_start > 0:
        # Reconstruct: keep <?php, remove broken block, keep rest
        content = '<?php\n' + '\n'.join(lines[clean_start:])
        print(f'  Rebuilt file starting from line {clean_start+1}')

# Verify no more backslash-dollar
if '\\$' in content:
    # Last resort: replace any remaining \$ with $
    import re
    content = re.sub(r'\\\$', '$', content)
    print('  Replaced remaining \\$ with $')

# Write clean file
with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print('FIXED: php/config.php')
print()

# Verify
lines = content.split('\n')
print('First 12 lines now:')
for i, line in enumerate(lines[:12], 1):
    print(f'  {i:2}: {line}')

print()
print('Checks:')
print('  starts with <?php:   ', content.startswith('<?php'))
print('  no backslash-dollar: ', '\\$' not in content)
print('  APP_URL present:     ', "APP_URL" in content)

import subprocess
r = subprocess.run(['/usr/bin/php8.3', '-l', path], capture_output=True, text=True)
if 'No syntax errors' in r.stdout:
    print('  PHP syntax: OK')
else:
    # try php8.1
    r2 = subprocess.run(['/usr/bin/php', '-l', path], capture_output=True, text=True)
    print('  PHP syntax:', (r.stdout + r2.stdout + r.stderr + r2.stderr).strip()[:100])