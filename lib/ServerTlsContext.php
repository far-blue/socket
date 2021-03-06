<?php

namespace Amp\Socket;

final class ServerTlsContext {
    const TLSv1_0 = \STREAM_CRYPTO_METHOD_TLSv1_0_SERVER;
    const TLSv1_1 = \STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;
    const TLSv1_2 = \STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;

    /** @var int */
    private $minVersion = \STREAM_CRYPTO_METHOD_TLSv1_0_SERVER;

    /** @var null|string */
    private $peerName = null;

    /** @var bool */
    private $verifyPeer = false;

    /** @var int */
    private $verifyDepth = 10;

    /** @var null|string */
    private $ciphers = null;

    /** @var null|string */
    private $caFile = null;

    /** @var null|string */
    private $caPath = null;

    /** @var bool */
    private $capturePeer = false;

    /** @var null|Certificate */
    private $defaultCertificate = null;

    /** @var Certificate[] */
    private $certificates = [];

    public function withMinimumVersion(int $version): self {
        if ($version !== self::TLSv1_0 && $version !== self::TLSv1_1 && $version !== self::TLSv1_2) {
            throw new \Error("Invalid minimum version, only TLSv1.0, TLSv1.1 or TLSv1.2 allowed");
        }

        $clone = clone $this;
        $clone->minVersion = $version;

        return $clone;
    }

    public function getMinimumVersion(): int {
        return $this->minVersion;
    }

    public function withPeerName(string $peerName = null): self {
        $clone = clone $this;
        $clone->peerName = $peerName;

        return $clone;
    }

    public function getPeerName() {
        return $this->peerName;
    }

    public function withPeerVerification(): self {
        $clone = clone $this;
        $clone->verifyPeer = true;

        return $clone;
    }

    public function withoutPeerVerification(): self {
        $clone = clone $this;
        $clone->verifyPeer = false;

        return $clone;
    }

    public function hasPeerVerification(): bool {
        return $this->verifyPeer;
    }

    public function withVerificationDepth(int $verifyDepth): self {
        if ($verifyDepth < 0) {
            throw new \Error("Invalid verification depth ({$verifyDepth}), must be greater than or equal to 0");
        }

        $clone = clone $this;
        $clone->verifyPeer = $verifyDepth;

        return $clone;
    }

    public function getVerificationDepth(): int {
        return $this->verifyDepth;
    }

    public function withCiphers(string $ciphers = null): self {
        $clone = clone $this;
        $clone->ciphers = $ciphers ?? \OPENSSL_DEFAULT_STREAM_CIPHERS;

        return $clone;
    }

    public function getCiphers(): string {
        return $this->ciphers;
    }

    public function withCaFile(string $cafile = null): self {
        $clone = clone $this;
        $clone->caFile = $cafile;

        return $clone;
    }

    public function getCaFile() {
        return $this->caFile;
    }

    public function withCaPath(string $capath = null): self {
        $clone = clone $this;
        $clone->caPath = $capath;

        return $clone;
    }

    public function getCaPath() {
        return $this->caPath;
    }

    public function withPeerCapturing(): self {
        $clone = clone $this;
        $clone->capturePeer = true;

        return $clone;
    }

    public function withoutPeerCapturing(): self {
        $clone = clone $this;
        $clone->capturePeer = false;

        return $clone;
    }

    public function hasPeerCapturing(): bool {
        return $this->capturePeer;
    }

    public function withDefaultCertificate(Certificate $defaultCertificate = null): self {
        $clone = clone $this;
        $clone->defaultCertificate = $defaultCertificate;

        return $clone;
    }

    public function getDefaultCertificate() {
        return $this->defaultCertificate;
    }

    public function withCertificates(array $certificates): self {
        foreach ($certificates as $certificate) {
            if (!$certificate instanceof Certificate) {
                throw new \TypeError("Expected an array of Certificate instances");
            }

            if (\PHP_VERSION_ID < 70200 && $certificate->getCertFile() !== $certificate->getKeyFile()) {
                throw new \Error(
                    "Different files for cert and key are not supported on this version of PHP. " .
                    "Please upgrade to PHP 7.2 or later."
                );
            }
        }

        $clone = clone $this;
        $clone->certificates = $certificates;

        return $clone;
    }

    public function getCertificates(): array {
        return $this->certificates;
    }

    public function toStreamContextArray(): array {
        $options = [
            "crypto_method" => $this->toStreamCryptoMethod(),
            "peer_name" => $this->peerName,
            "verify_peer" => $this->verifyPeer,
            "verify_peer_name" => $this->verifyPeer,
            "verify_depth" => $this->verifyDepth,
            "ciphers" => $this->ciphers ?? \OPENSSL_DEFAULT_STREAM_CIPHERS,
            "honor_cipher_order" => true,
            "single_dh_use" => true,
            "no_ticket" => true,
            "capture_peer_cert" => $this->capturePeer,
            "capture_peer_chain" => $this->capturePeer,
        ];

        if ($this->defaultCertificate !== null) {
            $options["local_cert"] = $this->defaultCertificate->getCertFile();

            if ($this->defaultCertificate->getCertFile() !== $this->defaultCertificate->getKeyFile()) {
                $options["local_pk"] = $this->defaultCertificate->getKeyFile();
            }
        }

        if ($this->certificates) {
            $options["SNI_server_certs"] = array_map(function (Certificate $certificate) {
                if ($certificate->getCertFile() === $certificate->getKeyFile()) {
                    return $certificate->getCertFile();
                }

                return [
                    "local_cert" => $certificate->getCertFile(),
                    "local_pk" => $certificate->getKeyFile(),
                ];
            }, $this->certificates);
        }

        if ($this->caFile !== null) {
            $options["cafile"] = $this->caFile;
        }

        if ($this->caPath !== null) {
            $options["capath"] = $this->caPath;
        }

        return ["ssl" => $options];
    }

    public function toStreamCryptoMethod(): int {
        return (~($this->minVersion - 1) & \STREAM_CRYPTO_METHOD_ANY_SERVER) & (~1);
    }
}
