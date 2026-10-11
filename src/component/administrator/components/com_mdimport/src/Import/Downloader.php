<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Import;

\defined('_JEXEC') or die;

/**
 * Downloads the repository archive and unpacks it.
 *
 * Straight to a file rather than through Joomla's Http client, which holds the
 * whole response in memory: the English help repository is some 55 MB, mostly
 * screenshots, and a site's memory_limit is often 128 MB.
 */
final class Downloader
{
    /**
     * @param   string|null  $caBundle  A CA bundle for hosts whose PHP has none configured
     */
    public function __construct(private readonly ?string $caBundle = null)
    {
    }

    /**
     * Fetch a URL to a file.
     *
     * @throws \RuntimeException when the download fails
     */
    public function download(string $url, string $target): void
    {
        $this->ensureDirectory(\dirname($target));

        if (\function_exists('curl_init')) {
            $this->withCurl($url, $target);
        } else {
            $this->withStreams($url, $target);
        }

        if (!is_file($target) || filesize($target) === 0) {
            throw new \RuntimeException('The download from ' . $url . ' is empty.');
        }
    }

    /**
     * Unpack an archive into a folder, and return the folder the repository
     * is in - GitHub puts everything one level down, in `<repository>-<branch>/`.
     *
     * @throws \RuntimeException when the archive cannot be unpacked
     */
    public function extract(string $archive, string $target): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP\'s zip extension is needed to unpack the download.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('The download is not a zip archive.');
        }

        $this->ensureDirectory($target);

        // Entry by entry rather than extractTo(): anything that is not under
        // the archive's own top folder, or that would climb out of it, is left
        // out instead of trusted.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                continue;
            }

            if (str_ends_with($name, '/')) {
                $this->ensureDirectory($target . '/' . $name);

                continue;
            }

            $this->ensureDirectory(\dirname($target . '/' . $name));

            $stream = $zip->getStream($name);

            if ($stream === false) {
                continue;
            }

            $out = fopen($target . '/' . $name, 'wb');

            if ($out !== false) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
            }

            fclose($stream);
        }

        $zip->close();

        $folders = glob($target . '/*', GLOB_ONLYDIR) ?: [];

        if (\count($folders) === 1 && is_dir($folders[0] . '/articles')) {
            return str_replace('\\', '/', $folders[0]);
        }

        if (is_dir($target . '/articles')) {
            return $target;
        }

        throw new \RuntimeException('The download does not look like a Jdocmanual data repository: it has no articles folder.');
    }

    private function withCurl(string $url, string $target): void
    {
        $file = fopen($target, 'wb');

        if ($file === false) {
            throw new \RuntimeException('Cannot write ' . $target);
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_FILE           => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_USERAGENT      => 'Joomla MD Import',
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);

        if ($this->caBundle !== null && is_file($this->caBundle)) {
            curl_setopt($curl, CURLOPT_CAINFO, $this->caBundle);
        }

        $ok    = curl_exec($curl);
        $error = curl_error($curl);
        $code  = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        fclose($file);

        if ($ok === false || $code >= 400) {
            @unlink($target);

            throw new \RuntimeException(\sprintf('Downloading %s failed (%s).', $url, $error !== '' ? $error : 'HTTP ' . $code));
        }
    }

    private function withStreams(string $url, string $target): void
    {
        $context = stream_context_create([
            'http' => ['follow_location' => 1, 'timeout' => 600, 'user_agent' => 'Joomla MD Import'],
            'ssl'  => $this->caBundle !== null && is_file($this->caBundle) ? ['cafile' => $this->caBundle] : [],
        ]);

        $in = @fopen($url, 'rb', false, $context);

        if ($in === false) {
            throw new \RuntimeException('Downloading ' . $url . ' failed. Is allow_url_fopen or the curl extension available?');
        }

        $out = fopen($target, 'wb');

        if ($out === false) {
            fclose($in);

            throw new \RuntimeException('Cannot write ' . $target);
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create ' . $directory);
        }
    }
}
