<?php

declare(strict_types=1);
namespace Adawolfa\MemoryStream;
use FFI;

/**
 * FFI memory stream wrapper.
 */
final class MemoryStreamWrapper
{

	public const PROTOCOL = 'ffi.memory';

	/**
	 * Pointer to the buffer.
	 */
	private FFI\CData $ptr;

	/**
	 * Buffer size.
	 */
	private int $size;

	/**
	 * Current seek position.
	 */
	private int $seek = 0;

	/**
	 * Access flags.
	 */
	private bool $readable = false, $writable = false;

	/**
	 * Registers the wrapper.
	 */
	public static function register(): void
	{
		if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
			stream_wrapper_register(self::PROTOCOL, self::class);
		}
	}

	public function stream_open(string $path, string $mode, int $options): bool
	{
		if (!preg_match('~^[^:]*://(?<addr>\d+);(?<size>\d+)$~', $path, $matches)
			|| (int) $matches['size'] <= 0
			|| (int) $matches['addr'] <= 0
		) {
			trigger_error(sprintf('Incorrect buffer specification for %s stream wrapper.', self::PROTOCOL), E_USER_WARNING);
			return false;
		}

		switch ($mode) {

			case 'r':
				$this->readable = true;
				break;

			case 'w':
				$this->writable = true;
				break;

			case 'rw':
				$this->readable = $this->writable = true;
				break;

			default:
				trigger_error(sprintf("Unsupported mode '%s'.", $mode), E_USER_WARNING);
				return false;

		}

		$this->ptr  = self::add(FFI::new('void *'), (int) $matches['addr']);
		$this->size = (int) $matches['size'];

		return true;
	}

	public function stream_read(int $count): string|false
	{
		if (!$this->readable) {
			trigger_error('Stream is not readable.', E_USER_WARNING);
			return false;
		}

		if ($this->stream_eof()) {
			return '';
		}

		$len         = $this->seek + $count > $this->size ? $this->size - $this->seek : $count;
		$data        = FFI::string(self::add($this->ptr, $this->seek), $len);
		$this->seek += $len;

		return $data;
	}

	public function stream_write(string $data): int
	{
		if (!$this->writable) {
			trigger_error('Stream is not writable.', E_USER_WARNING);
			return 0;
		}

		$len = strlen($data);
		$len = $this->seek + $len > $this->size ? $this->size - $this->seek : $len;

		if ($len < strlen($data)) {
			trigger_error(sprintf('Failed to write %d bytes past end of buffer.', strlen($data) - $len), E_USER_WARNING);
		}

		FFI::memcpy(self::add($this->ptr, $this->seek), $data, $len);
		$this->seek += $len;

		return $len;
	}

	public function stream_eof(): bool
	{
		return $this->seek >= $this->size;
	}

	public function stream_seek(int $offset, int $whence = SEEK_SET): bool
	{
		$seek = match ($whence) {
			SEEK_SET => $offset,
			SEEK_CUR => $this->seek + $offset,
			SEEK_END => $this->size + $offset,
		};

		$this->seek = min(max($seek, 0), $this->size);

		return true;
	}

	public function stream_tell(): int
	{
		return $this->seek;
	}

	public function stream_stat(): array
	{
		return ['size' => $this->size];
	}

	public function stream_set_option(int $option, int $arg1, int $arg2): bool
	{
		return false;
	}

	private static function add(FFI\CData $ptr, int $offset): FFI\CData
	{
		/** @noinspection PhpArithmeticTypeCheckInspection */
		return $ptr + $offset;
	}

}