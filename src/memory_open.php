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
	$spec = sprintf(
		'ffi.memory://%d;%d',
		FFI::cast(PHP_INT_SIZE === 8 ? 'unsigned long long' : 'unsigned int', $ptr)->cdata,
		$size,
	);

	return fopen($spec, $mode);
}