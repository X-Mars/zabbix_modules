<?php

namespace Modules\ZabbixBackupRestore\Lib;

require_once __DIR__.'/Compat.php';

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** Reads and writes the module's block YAML one configuration record at a time. */
final class BundleYaml {

	public static function write($stream, array $data): void {
		$bytes = 0;
		$write = static function (string $text) use ($stream, &$bytes): void {
			$length = strlen($text);
			$bytes += $length;
			Bundle::checkSize($bytes);
			for ($offset = 0; $offset < $length; $offset += $written) {
				$written = fwrite($stream, $offset === 0 ? $text : substr($text, $offset));
				if ($written === false || $written === 0) {
					throw new RuntimeException(LanguageManager::t('Could not write the temporary backup file.'));
				}
			}
		};

		$write(Bundle::FORMAT.":\n");
		foreach ($data as $key => $value) {
			if (($key === 'configuration' || $key === 'records') && $value) {
				$write('  '.$key.":\n");
				foreach ($value as $section => $rows) {
					self::writeField($write, $section, $rows, 4, $section !== 'version');
				}
			}
			else {
				self::writeField($write, $key, $value, 2, $key === 'host_encryption');
			}
		}
	}

	private static function writeField(callable $write, string $key, $value, int $indent, bool $list): void {
		if (!$list || !$value) {
			$write(self::indentedYaml([$key => $value], $indent));
			return;
		}

		$write(str_repeat(' ', $indent).$key.":\n");
		foreach ($value as $row) {
			$write(self::indentedYaml([$row], $indent + 2));
		}
	}

	private static function indentedYaml(array $value, int $indent): string {
		$yaml = Yaml::dump($value, 80, 2);
		return preg_replace('/^(?=.)/m', str_repeat(' ', $indent), $yaml);
	}

	public static function read($stream, int $size): array {
		Bundle::checkSize($size);
		$line = fgets($stream);
		if ($line !== false && str_starts_with($line, "\xEF\xBB\xBF")) {
			$line = substr($line, 3);
		}
		// Accept optional blank lines, comments and a YAML document start before the root mapping.
		self::skipComments($stream, $line);
		if ($line !== false && rtrim($line) === '---') {
			$line = fgets($stream);
			self::skipComments($stream, $line);
		}
		if ($line === false || !preg_match('/^'.Bundle::FORMAT.':[ \t]*(?:#.*)?$/D', rtrim($line, "\r\n"))) {
			throw self::malformed();
		}

		$line = fgets($stream);
		$data = self::fields($stream, $line, 2, true);
		if ($line !== false && rtrim($line) === '...') {
			$line = fgets($stream);
			self::skipComments($stream, $line);
		}
		if ($line !== false || !feof($stream)) {
			throw self::malformed();
		}

		return $data;
	}

	private static function fields($stream, &$line, int $indent, bool $root): array {
		$data = [];
		$prefix = str_repeat(' ', $indent);
		self::skipComments($stream, $line);
		while ($line !== false && str_starts_with($line, $prefix)) {
			$header = substr($line, $indent);
			if (!preg_match('/^([a-z][a-z0-9_]*):(?:[ \t]+(.*))?$/D', rtrim($header, "\r\n"), $match)
					|| array_key_exists($match[1], $data)) {
				throw self::malformed();
			}

			$key = $match[1];
			$line = fgets($stream);
			$inline = trim($match[2] ?? '');
			$block = $inline === '' || str_starts_with($inline, '#');
			if ($block && $root && in_array($key, ['configuration', 'records'], true)) {
				$data[$key] = self::fields($stream, $line, $indent + 2, false);
			}
			elseif ($block && (!$root || $key === 'host_encryption')) {
				$data[$key] = self::rows($stream, $line, $indent + 2);
			}
			else {
				$value = self::parse($header.self::readChildren($stream, $line, $indent));
				if (!is_array($value) || array_keys($value) !== [$key]) {
					throw self::malformed();
				}
				$data[$key] = $value[$key];
			}
			self::skipComments($stream, $line);
		}

		return $data;
	}

	private static function rows($stream, &$line, int $indent): array {
		$rows = [];
		$prefix = str_repeat(' ', $indent);
		self::skipComments($stream, $line);
		while ($line !== false && str_starts_with($line, $prefix.'-')) {
			$head = substr($line, $indent);
			$line = fgets($stream);
			$row = self::parse($head.self::readChildren($stream, $line, $indent));
			if (!is_array($row) || array_keys($row) !== [0] || !is_array($row[0])) {
				throw self::malformed();
			}
			$rows[] = $row[0];
			self::skipComments($stream, $line);
		}
		return $rows;
	}

	private static function readChildren($stream, &$line, int $indent): string {
		$text = '';
		$prefix = str_repeat(' ', $indent + 1);
		while ($line !== false && (str_starts_with($line, $prefix) || trim($line) === '')) {
			$text .= trim($line) === '' ? "\n" : substr($line, $indent);
			$line = fgets($stream);
		}
		return $text;
	}

	private static function skipComments($stream, &$line): void {
		while ($line !== false && (trim($line) === '' || str_starts_with(ltrim($line), '#'))) {
			$line = fgets($stream);
		}
	}

	private static function parse(string $yaml) {
		// Generated backups use block collections and quoted scalars.
		$tokens = <<<'PATTERN'
~(?:"(?:[^"\\\r\n]++|\\.)*"|'(?:[^'\r\n]++|'')*'|\[ *\]|\{ *\})(*SKIP)(*F)|^ {150}|[\[\]{}]|(?:^|[ \t,:])[&*!][^\s\[\]{},]+~m
PATTERN;
		if (preg_match($tokens, $yaml) !== 0) {
			throw self::malformed();
		}

		try {
			return Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
		}
		catch (ParseException $exception) {
			throw self::malformed();
		}
	}

	private static function malformed(): RuntimeException {
		return new RuntimeException(LanguageManager::t('The YAML backup is malformed or uses unsupported syntax.'));
	}
}
