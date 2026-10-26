import os
import re
import json

PROJECT_ROOT = r"./"

findings = {
    "critical": [],
    "high": [],
    "medium": [],
    "low": []
}

DANGEROUS_PATTERNS = {
    "SQL Injection":
        [
            r"SELECT .* \$_(GET|POST)",
            r"INSERT .* \$_(GET|POST)",
            r"UPDATE .* \$_(GET|POST)",
            r"DELETE .* \$_(GET|POST)"
        ],

    "Command Injection":
        [
            r"shell_exec\(",
            r"exec\(",
            r"system\(",
            r"passthru\("
        ],

    "File Inclusion":
        [
            r"include\s*\(\s*\$_",
            r"require\s*\(\s*\$_"
        ],

    "Hardcoded Secrets":
        [
            r"password\s*=",
            r"api_key",
            r"secret_key",
            r"paypal_secret"
        ]
}

for root, dirs, files in os.walk(PROJECT_ROOT):

    for file in files:

        if file.endswith(".php"):

            filepath = os.path.join(root, file)

            try:

                content = open(
                    filepath,
                    encoding="utf8",
                    errors="ignore"
                ).read()

                for category, patterns in DANGEROUS_PATTERNS.items():

                    for pattern in patterns:

                        if re.search(pattern, content,
                                     re.IGNORECASE):

                            findings["high"].append({
                                "file": filepath,
                                "issue": category
                            })

            except:
                pass

with open("security_report.json", "w") as f:
    json.dump(findings, f, indent=4)

print("Security scan complete")