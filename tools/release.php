<?php declare(strict_types=1);

$theme_file = __DIR__ . '/../style.css';
if (!file_exists($theme_file)) exit("❌ Theme file not found: {$theme_file}\n");

$contents = file_get_contents($theme_file);
if (!strpos($contents, 'Version:')) exit("❌ Version not found in plugin header.\n");

$lines = preg_split('/\R/', $contents);
$new_version = '';

foreach ($lines as $index => $line) {
    if (str_contains($line, 'Version:')) {

        if (preg_match('/Version:\s*([\d\.]+)/i', $line, $matches)) {
            $current_version = $matches[1];
        } else {
            exit("❌ Version number not found in line.\n");
        }

        $parts = array_map('intval', explode('.', $current_version));
        while (count($parts) < 3) $parts[] = 0;

        [$major, $minor, $patch] = $parts;

        if ($patch < 9) {
            $patch++;
        } else {
            $minor++;
            $patch = 0;
        }

        $new_version = "{$major}.{$minor}.{$patch}";
        $new_line = preg_replace('/(Version:\s*)([\d\.]+)/i', '${1}' . $new_version, $line);
        $lines[$index] = $new_line;

        echo "✅ Version updated: {$current_version} → {$new_version}\n";
        break;
    }
}

$new_contents = implode(PHP_EOL, $lines);

if (file_put_contents($theme_file, $new_contents)) {
    echo "✅ File saved.\n";

    shell_exec("git add {$theme_file}");
    shell_exec("git commit -m \"🔖 Release v{$new_version}\"");
    shell_exec("git tag v{$new_version}");
    shell_exec("git push origin main");
    shell_exec("git push origin v{$new_version}");

    echo "🚀 Release v{$new_version} pushed and workflow triggered.\n";
};



