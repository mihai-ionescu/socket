<?php

namespace Amp\Socket;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Failure;
use Amp\Promise;

class Socket implements InputStream, OutputStream {
    /** @var \Amp\ByteStream\ResourceInputStream */
    private $reader;

    /** @var \Amp\ByteStream\ResourceOutputStream */
    private $writer;

    /**
     * @param resource $resource Stream resource.
     * @param int      $chunkSize Read and write chunk size.
     *
     * @throws \Error If a stream resource is not given for $resource.
     */
    public function __construct($resource, int $chunkSize = 65536) {
        $this->reader = new ResourceInputStream($resource, $chunkSize);
        $this->writer = new ResourceOutputStream($resource, $chunkSize);
    }

    /**
     * Raw stream socket resource.
     *
     * @return resource|null
     */
    public function getResource() {
        return $this->reader->getResource();
    }

    /**
     * Enables encryption on this socket.
     *
     * @param ClientTlsContext $tlsContext
     *
     * @return Promise
     */
    public function enableCrypto(ClientTlsContext $tlsContext): Promise {
        if (($resource = $this->reader->getResource()) === null) {
            return new Failure(new ClosedException("The socket has been closed"));
        }

        return Internal\enableCrypto($resource, $tlsContext->toStreamContextArray());
    }

    /**
     * Disables encryption on this socket.
     *
     * @return Promise
     */
    public function disableCrypto(): Promise {
        if (($resource = $this->reader->getResource()) === null) {
            return new Failure(new ClosedException("The socket has been closed"));
        }

        return Internal\disableCrypto($resource);
    }

    /** @inheritdoc */
    public function read(): Promise {
        return $this->reader->read();
    }

    /** @inheritdoc */
    public function write(string $data): Promise {
        return $this->writer->write($data);
    }

    /** @inheritdoc */
    public function end(string $data = ""): Promise {
        $promise = $this->writer->end($data);
        $promise->onResolve(function () {
            $this->close();
        });

        return $promise;
    }

    /**
     * Force closes the socket, failing any pending reads or writes.
     */
    public function close() {
        $this->reader->close();
        $this->writer->close();
    }
}
