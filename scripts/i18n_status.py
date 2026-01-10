#!/usr/bin/env python3
import os
import re
import sys

# Regex patterns to find translation keys in the code
# PHP: (__('key'))
# JS: t('key'), tFallback('key', ...)
# Using \b to ensure we match the function name exactly and not as part of another word (like showUndoToast)
PHP_REGEX = re.compile(r"\b__\(\s*[\x22\x27]([^\x22\x27]+)[\x22\x27]\s*\)")
JS_REGEX = re.compile(r"\bt(?:Fallback)?\(\s*[\x22\x27]([^\x22\x27]+)[\x22\x27]")

# Regex to find keys in translation files (src/i18n/*.php)
# Usually: 'key' => 'value', or "key" => "value"
LANG_FILE_REGEX = re.compile(r"[\x22\x27]([^\x22\x27]+)[\x22\x27]\s*=>")

def collect_keys_from_code(root_dir):
    keys = set()
    
    # Directories to scan
    scan_dirs = [
        os.path.join(root_dir, 'templates'),
        os.path.join(root_dir, 'src'),
        os.path.join(root_dir, 'assets', 'js'),
    ]
    
    for scan_dir in scan_dirs:
        if not os.path.exists(scan_dir):
            continue
            
        for root, _, files in os.walk(scan_dir):
            for file in files:
                if file.endswith(('.php', '.js')):
                    path = os.path.join(root, file)
                    try:
                        with open(path, 'r', encoding='utf-8') as f:
                            for line in f:
                                line = line.strip()
                                # Skip comment lines
                                if line.startswith(('//', '*', '/*')):
                                    continue
                                    
                                if file.endswith('.php'):
                                    keys.update(PHP_REGEX.findall(line))
                                
                                if file.endswith('.js'):
                                    # Refined JS match: only match full literals (followed by , or ))
                                    # This avoids catching prefixes like 'status_' in t('status_' + ...)
                                    matches = re.findall(r"\bt(?:Fallback)?\(\s*[\x22\x27]([^\x22\x27]+)[\x22\x27]\s*[,\)]", line)
                                    keys.update(matches)
                    except Exception as e:
                        print(f"Error reading {path}: {e}", file=sys.stderr)
                        
    return keys

def get_translation_files(root_dir):
    i18n_dir = os.path.join(root_dir, 'src', 'i18n')
    if not os.path.exists(i18n_dir):
        return {}
        
    lang_files = {}
    for file in os.listdir(i18n_dir):
        if file.endswith('.php') and file not in ('Languages.php', 'Translator.php'):
            lang_code = file[:-4]
            lang_files[lang_code] = os.path.join(i18n_dir, file)
            
    return lang_files

def collect_keys_from_lang_file(file_path):
    keys = set()
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
            keys.update(LANG_FILE_REGEX.findall(content))
    except Exception as e:
        print(f"Error reading {file_path}: {e}", file=sys.stderr)
    return keys

def main():
    root_dir = os.getcwd()
    
    print(f"Scanning codebase in: {root_dir}")
    used_keys = collect_keys_from_code(root_dir)
    print(f"Found {len(used_keys)} unique keys in code.")
    
    lang_files = get_translation_files(root_dir)
    if not lang_files:
        print("No translation files found in src/i18n/")
        return

    print("\nTranslation Status:")
    print("-" * 65)
    print(f"{ 'Language':<12} | { 'Translated':<12} | { 'Missing':<12} | { 'Coverage':<10}")
    print("-" * 65)
    
    used_keys_list = sorted(list(used_keys))
    
    for lang, path in sorted(lang_files.items()):
        lang_keys = collect_keys_from_lang_file(path)
        
        translated = [k for k in used_keys_list if k in lang_keys]
        missing = [k for k in used_keys_list if k not in lang_keys]
        
        coverage = (len(translated) / len(used_keys_list) * 100) if used_keys_list else 100
        
        print(f"{lang:<12} | {len(translated):<12} | {len(missing):<12} | {coverage:>8.1f}%")

    for lang, path in sorted(lang_files.items()):
        if lang != 'en': continue
        
        lang_keys = collect_keys_from_lang_file(path)
        missing = sorted([k for k in used_keys_list if k not in lang_keys])
        if missing:
            print(f"\nMissing keys for '{lang}':")
            for key in missing:
                print(f"  - {key}")

if __name__ == "__main__":
    main()
