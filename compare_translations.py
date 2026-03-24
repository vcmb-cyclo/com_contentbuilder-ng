#!/usr/bin/env python3
import re

# Admin files
with open(
    "administrator/languages/en-GB/admin/en-GB.com_contentbuilderng.ini", "r"
) as f:
    en_admin = f.read()

with open(
    "administrator/languages/de-DE/admin/de-DE.com_contentbuilderng.ini", "r"
) as f:
    de_admin = f.read()

en_keys = set(re.findall(r"^(COM_[A-Z_0-9]+)=", en_admin, re.MULTILINE))
de_keys = set(re.findall(r"^(COM_[A-Z_0-9]+)=", de_admin, re.MULTILINE))

missing = en_keys - de_keys
print("ADMIN - Keys in EN but NOT in DE:")
for k in sorted(missing):
    print(f"  {k}")
print(f"Total missing in admin: {len(missing)}")

# Site files
with open(
    "administrator/languages/en-GB/site/en-GB.com_contentbuilderng.ini", "r"
) as f:
    en_site = f.read()

with open(
    "administrator/languages/de-DE/site/de-DE.com_contentbuilderng.ini", "r"
) as f:
    de_site = f.read()

en_keys_site = set(re.findall(r"^(COM_[A-Z_0-9]+)=", en_site, re.MULTILINE))
de_keys_site = set(re.findall(r"^(COM_[A-Z_0-9]+)=", de_site, re.MULTILINE))

missing_site = en_keys_site - de_keys_site
print("\nSITE - Keys in EN but NOT in DE:")
for k in sorted(missing_site):
    print(f"  {k}")
print(f"Total missing in site: {len(missing_site)}")
