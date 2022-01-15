<?php

declare(strict_types=1);
namespace Tests\Adawolfa\MemoryStream;
use PHPUnit\Framework\TestCase;
use FFI;
use function Adawolfa\MemoryStream\memory_open;
use TypeError;

final class MemoryStreamWrapperTest extends TestCase
{

	public function testOpen(): void
	{
		$data = FFI::new('char');
		$stream = memory_open(FFI::addr($data), 'r', 1);
		$this->assertTrue(is_resource($stream));
	}

	public function testOpenError(): void
	{
		$this->assertFalse(@fopen('ffi.memory://abc', 'r'));
		$this->assertFalse(@fopen('ffi.memory://123;0', 'r'));
		$this->assertFalse(@fopen('ffi.memory://123;0', 'a+'));
	}

	public function testRead(): void
	{
		$data = FFI::new('char[10]');
		FFI::memcpy($data, '0123456789', 10);

		$stream = memory_open(FFI::addr($data), 'r', 10);

		$this->assertSame('01234', fread($stream, 5));
		$this->assertSame('56789', fread($stream, 7));
		$this->assertSame('', fread($stream, 2));
		$this->assertTrue(feof($stream));
	}

	public function testReadErrorNotReadable(): void
	{
		$data = FFI::new('char');
		$stream = memory_open(FFI::addr($data), 'w', 1);
		$this->assertFalse(@fread($stream, 1));
	}

	public function testWrite(): void
	{
		$data = FFI::new('char[11]');
		$stream = memory_open(FFI::addr($data), 'w', 11);

		$this->assertSame(5, fwrite($stream, 'hello'));
		$this->assertSame('hello', FFI::string($data, 5));

		$this->assertSame(6, fwrite($stream, ' world'));
		$this->assertSame('hello world', FFI::string($data, 11));

		$this->assertTrue(feof($stream));
	}

	public function testWriteErrorNotWritable(): void
	{
		$data = FFI::new('char');
		$stream = memory_open(FFI::addr($data), 'r', 1);
		$this->assertSame(0, @fwrite($stream, 'a'));
	}

	public function testWriteErrorPastBuffer(): void
	{
		$data = FFI::new('char');
		$stream = memory_open(FFI::addr($data), 'w', 3);
		$this->assertSame(3, @fwrite($stream, 'hello'));
		$this->assertSame(0, @fwrite($stream, 'hello'));
	}

	public function testReadWrite(): void
	{
		$data = FFI::new('char[3]');
		$stream = memory_open(FFI::addr($data), 'rw', 3);
		$this->assertSame(3, fwrite($stream, 'foo'));
		$this->assertSame(0, fseek($stream, 0));
		$this->assertSame('foo', stream_get_contents($stream));
	}

	public function testSeekTell(): void
	{
		$data = FFI::new('char[10]');
		FFI::memcpy($data, '0123456789', 10);

		$stream = memory_open(FFI::addr($data), 'r', 10);
		$this->assertSame(0, ftell($stream));

		$this->assertSame('01234', fread($stream, 5));
		$this->assertSame(5, ftell($stream));

		$this->assertSame('56789', fread($stream, 7));
		$this->assertSame(10, ftell($stream));

		$this->assertSame('', fread($stream, 2));
		$this->assertSame(10, ftell($stream));

		fseek($stream, 5);
		$this->assertSame(5, ftell($stream));
		$this->assertSame('5', fread($stream, 1));

		fseek($stream, 2, SEEK_CUR);
		$this->assertSame(8, ftell($stream));
		$this->assertSame('8', fread($stream, 1));

		fseek($stream, -1, SEEK_END);
		$this->assertSame(9, ftell($stream));
		$this->assertSame('9', fread($stream, 1));
	}

	public function testStat(): void
	{
		$data = FFI::new('char[10]');
		$stream = memory_open(FFI::addr($data), 'r', 10);
		$this->assertSame(10, fstat($stream)['size']);
	}

	public function testClosed(): void
	{
		$this->expectException(TypeError::class);
		$data = FFI::new('char[10]');
		$stream = memory_open(FFI::addr($data), 'r', 10);
		fclose($stream);
		$this->assertFalse(@fread($stream, 10));
	}

}