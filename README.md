# FFI memory stream wrapper

This library provides a [PHP stream wrapper](https://www.php.net/manual/en/class.streamwrapper.php) for direct memory access using standard stream functions. It's intended to be used by FFI wrappers as a method of providing safe, convenient and known way of accessing data.

## Installation

~~~bash
composer install adawolfa/ffi-memory-stream
~~~

## Usage

There's a `memory_open()` function, which creates a stream to the data from FFI pointer.

~~~php
$ptr    = $ffi->get_buffer($size);
$stream = Adawolfa\MemoryStream\memory_open($ptr, 'r', $size);
$data   = fread($ptr, 10);
~~~

You can make the stream read-only (`r`), write-only (`w`) or unrestricted (`rw`). The wrapper ensures that the user operates within the stream boundaries thanks to the `$size` argument, which is mandatory.

You, as a callee, should `fclose()` the stream explicitly once the pointer becomes invalid. Reading from or writing into such stream emits a warning, but unlike accessing the data via an invalid pointer, doesn't cause the process to crash, making it much easier for caller to debug.