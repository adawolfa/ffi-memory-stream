<?php

declare(strict_types=1);
namespace Adawolfa\MemoryStream;
use FFI;

/**
 * Creates a memory stream from pointer.
 *
 * @param string $mode can be one of r, w or rw
 * @param int    $size size of the buffer past which you cannot read from or write into
 *
 * @return resource|false
 */
function memory_open(FFI\CData $ptr, string $mode, int $size)
{
	$ptrSize  = FFI::type('void *')->getSize();
	$ptrBytes = FFI::new(FFI::arrayType(FFI::type('unsigned char'), [$ptrSize]));
	FFI::memcpy(FFI::addr($ptrBytes), FFI::addr($ptr), $ptrSize);

	for ($addr = '0x', $i = $ptrSize - 1; $i >= 0; $i--) {
		$addr .= str_pad(dechex($ptrBytes[$i]), 2, '0', STR_PAD_LEFT);
	}

	$spec = sprintf('%s://%s;%d', MemoryStreamWrapper::PROTOCOL, $addr, $size);
	return fopen($spec, $mode);
}