<?php
declare(strict_types=1);

namespace App\Services\Public\Exception;

use App\Common\Exception\AppControllerException;

/**
 * Class PublicAPIException
 * @package App\Services\Public\Exception
 */
class PublicAPIException extends AppControllerException
{
    /** @var string|null */
    private ?string $param = null;

    /**
     * @param string $param
     * @param string $msg
     * @param int $code
     * @param \Throwable|null $prev
     * @return static
     */
    public static function Param(string $param, string $msg, int $code, ?\Throwable $prev): static
    {
        return (new self($msg, $code, $prev))->setParam($param);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setParam(string $key): self
    {
        $this->param = $key;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getParam(): ?string
    {
        return $this->param;
    }
}
