<?php

namespace App\Services;

use App\Exceptions\InvalidScormPackage;
use App\Models\ScormCourse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

class ScormPackageImporter
{
    private const DESCRIPTION = 'SCORM դասընթաց · Articulate 360';

    private const CATEGORY = 'SCORM ԴԱՍԸՆԹԱՑ';

    private const BAD_STRUCTURE = 'ZIP փաթեթի կառուցվածքը սխալ է։';

    private const BAD_PATH = 'ZIP փաթեթը պարունակում է անվավեր ֆայլի ուղի։';

    /** Rejects absolute paths, backslashes, NUL bytes, drive letters and dot segments. */
    public static function isSafePath(string $path): bool
    {
        if (strlen($path) >= 800 || str_starts_with($path, '/') || str_contains($path, '\\')
            || str_contains($path, "\0") || preg_match('/^[a-z]:/i', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }

        return true;
    }

    public function import(UploadedFile $file, ?string $title): ScormCourse
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            throw new InvalidScormPackage(self::BAD_STRUCTURE);
        }

        try {
            $entries = $this->readEntries($zip);
            $launch = $this->findLaunchPath($zip, $entries);

            $id = bin2hex(random_bytes(8));
            $destination = config('scorm.storage_path').DIRECTORY_SEPARATOR.$id;

            try {
                $this->extract($zip, $entries, $destination);

                return ScormCourse::create([
                    'id' => $id,
                    'title' => $this->titleFor($title, $file->getClientOriginalName()),
                    'description' => self::DESCRIPTION,
                    'category' => self::CATEGORY,
                    'launch_path' => $launch,
                ]);
            } catch (Throwable $e) {
                File::deleteDirectory($destination);

                throw $e;
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Validates the whole archive before anything is written to disk.
     *
     * @return array<string, array{index: int, size: int}> file entries keyed by path
     */
    private function readEntries(ZipArchive $zip): array
    {
        if ($zip->numFiles > config('scorm.max_entries')) {
            throw new InvalidScormPackage(self::BAD_STRUCTURE);
        }

        $entries = [];
        $expanded = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);

            if ($stat === false) {
                throw new InvalidScormPackage(self::BAD_STRUCTURE);
            }

            $name = str_replace('\\', '/', $stat['name']);

            if (($stat['encryption_method'] ?? ZipArchive::EM_NONE) !== ZipArchive::EM_NONE) {
                throw new InvalidScormPackage('Գաղտնաբառով պաշտպանված ZIP ֆայլը չի աջակցվում։');
            }

            if (str_ends_with($name, '/')) {
                continue; // Directories are recreated from the file paths below.
            }

            if (! self::isSafePath($name)) {
                throw new InvalidScormPackage(self::BAD_PATH);
            }

            $expanded += $stat['size'];

            if ($expanded > config('scorm.max_expanded_bytes')) {
                throw new InvalidScormPackage('ZIP փաթեթի բացված չափը չափազանց մեծ է։');
            }

            if (! in_array($stat['comp_method'], [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE], true)) {
                throw new InvalidScormPackage('Այս ZIP սեղմման եղանակը չի աջակցվում։');
            }

            $entries[$name] = ['index' => $i, 'size' => $stat['size']];
        }

        return $entries;
    }

    /** @param array<string, array{index: int, size: int}> $entries */
    private function findLaunchPath(ZipArchive $zip, array $entries): string
    {
        $manifest = null;

        foreach (array_keys($entries) as $name) {
            if (preg_match('~(^|/)imsmanifest\.xml$~i', $name)) {
                $manifest = $name;
                break;
            }
        }

        if ($manifest === null) {
            throw new InvalidScormPackage('Չգտնվեց imsmanifest.xml ֆայլը։ Արտահանեք SCORM 1.2 կամ SCORM 2004 փաթեթ Articulate-ից։');
        }

        $xml = (string) $zip->getFromIndex($entries[$manifest]['index']);
        $manifestDir = dirname($manifest);

        $itemId = preg_match('/<item\b[^>]*\bidentifierref=["\']([^"\']+)["\'][^>]*>/i', $xml, $m) ? $m[1] : null;
        preg_match_all('/<resource\b([^>]*)>/i', $xml, $m);
        $resources = $m[1];

        $resource = null;
        foreach ($resources as $attrs) {
            if ($itemId !== null && preg_match('/\bidentifier=["\']([^"\']+)["\']/i', $attrs, $id) && $id[1] === $itemId) {
                $resource = $attrs;
                break;
            }
        }
        foreach ($resource === null ? $resources : [] as $attrs) {
            if (preg_match('/scormtype=["\']sco["\']/i', $attrs)) {
                $resource = $attrs;
                break;
            }
        }
        $resource ??= $resources[0] ?? null;

        $launch = null;
        if ($resource !== null && preg_match('/\bhref=["\']([^"\']+)["\']/i', $resource, $m)) {
            $launch = ($manifestDir === '.' ? '' : $manifestDir.'/').$m[1];
        }

        if ($launch === null) {
            foreach (array_keys($entries) as $name) {
                if (preg_match('~(^|/)(story\.html|index_lms\.html)$~i', $name)) {
                    $launch = $name;
                    break;
                }
            }
        }

        // Manifest hrefs are relative to the manifest; fallback entries already use archive paths.
        $launch = $launch === null ? '' : $this->normalizePath($launch);

        // Articulate's launcher.html opens index_lms.html in a popup. Popups launched
        // automatically from the embedded course frame are blocked by browsers, so
        // launch the LMS player directly when the package includes it.
        if (preg_match('~(^|/)launcher\.html$~i', $launch)) {
            $dir = dirname($launch);
            $directPlayer = ($dir === '.' ? '' : $dir.'/').'index_lms.html';

            if (isset($entries[$directPlayer])) {
                $launch = $directPlayer;
            }
        }

        if ($launch === '' || ! self::isSafePath($launch) || ! isset($entries[$launch])) {
            throw new InvalidScormPackage('imsmanifest.xml-ում նշված դասընթացի գործարկման էջը չի գտնվել։');
        }

        return $launch;
    }

    /** Collapses "." and ".." segments. A path escaping the root keeps its ".." and fails validation. */
    private function normalizePath(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    /** @param array<string, array{index: int, size: int}> $entries */
    private function extract(ZipArchive $zip, array $entries, string $destination): void
    {
        File::ensureDirectoryExists($destination);

        foreach ($entries as $name => $entry) {
            $target = $destination.DIRECTORY_SEPARATOR.$name;
            File::ensureDirectoryExists(dirname($target));

            $in = $zip->getStreamIndex($entry['index']);
            $out = fopen($target, 'wb');

            if ($in === false || $out === false) {
                throw new InvalidScormPackage(self::BAD_STRUCTURE);
            }

            // Reads one byte past the declared size so a lying header can't expand unchecked.
            $written = stream_copy_to_stream($in, $out, $entry['size'] + 1);
            fclose($in);
            fclose($out);

            if ($written !== $entry['size']) {
                throw new InvalidScormPackage('ZIP ֆայլի բացված չափը չի համընկնում։');
            }
        }
    }

    private function titleFor(?string $title, string $filename): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            $title = pathinfo($filename, PATHINFO_FILENAME) ?: 'Դասընթաց';
        }

        return mb_substr($title, 0, 160);
    }
}
