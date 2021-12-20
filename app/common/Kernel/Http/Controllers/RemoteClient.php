<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Controllers;

use Comely\Http\Router\Request;

/**
 * Class RemoteClient
 * @package App\Common\Kernel\Http\Controllers
 */
class RemoteClient
{
    /** @var string */
    public readonly string $realIpAddress;
    /** @var string */
    public readonly string $ipAddress;
    /** @var int */
    public readonly int $port;
    /** @var string|null */
    public readonly ?string $origin;
    /** @var string|null */
    public readonly ?string $userAgent;

    /**
     * @param Request $req
     */
    public function __construct(Request $req)
    {
        $this->realIpAddress = strval($_SERVER["REMOTE_ADDR"]);
        $this->port = intval($_SERVER["REMOTE_PORT"] ?? 0);

        // Cloudflare OR X-Forwarded-For IP Address
        if ($req->headers->has("cf-connecting-ip")) {
            $userIpAddr = $req->headers->get("cf-connecting_-ip");
        } elseif ($req->headers->has("x-forwarded-for")) {
            $xff = explode(",", $req->headers->get("x-forwarded-for"));
            $userIpAddr = trim(preg_replace('/[^a-f0-9.:]/', '', strtolower($xff[0])));
        }

        $this->ipAddress = $userIpAddr ?? $this->realIpAddress;

        // Other Headers
        $this->origin = $req->headers->get("referer");
        $this->userAgent = $req->headers->get("user-agent");
    }
}
