<?php

declare(strict_types=1);
namespace Adawolfa\MemoryStream;
use FFI;

/**
 * FFI memory stream wrapper.
 *
 * Format: ffi.memory://<address>;<size>
 * Address is expected to be a hexadecimal number (big-endian), size must be a positive decimal number.
 * Example: fopen("ffi.memory://0x80004dfe5;1024", "r")
 */
final class MemoryStreamWrapper
{

	public const PROTOCOL = 'ffi.memory';

	/**
	 * Pointer to the buffer.
	 */
	private readonly FFI\CData $ptr;

	/**
	 * Buffer size.
	 */
	private readonly int $size;

	/**
	 * Current seek position.
	 */
	private int $seek = 0;

	/**
	 * Access flags.
	 */
	private readonly bool $readable, $writable;

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
		if (!preg_match('~^[^:]*://0x(?<addr>[0-9a-f]{1,' . (PHP_INT_SIZE * 2) . '});(?<size>\d+)$~i', $path, $matches)
			|| (int) $matches['size'] < 0
		) {
			trigger_error(sprintf('Incorrect buffer specification for %s stream wrapper.', self::PROTOCOL), E_USER_WARNING);
			return false;
		}

		$readable = $writable = false;

		switch ($mode) {

			case 'r':
				$readable = true;
				break;

			case 'w':
				$writable = true;
				break;

			case 'rw':
				$readable = $writable = true;
				break;

			default:
				trigger_error(sprintf("Unsupported mode '%s'.", $mode), E_USER_WARNING);
				return false;

		}

		$this->ptr      = FFI::new('void *');
		$this->size     = (int) $matches['size'];
		$this->readable = $readable;
		$this->writable = $writable;
		$ptrSize        = FFI::sizeof($this->ptr);
		$addrBytes      = FFI::new(FFI::arrayType(FFI::type('unsigned char'), [$ptrSize]));
		$hexAddr        = str_pad($matches['addr'], $ptrSize * 2, '0', STR_PAD_LEFT);

		foreach (array_reverse(str_split($hexAddr, 2)) as $i => $hByte) {
			$addrBytes[$i] = hexdec($hByte);
		}

		FFI::memcpy(FFI::addr($this->ptr), FFI::addr($addrBytes), $ptrSize);

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