<?php
declare(strict_types=1);

namespace App\Common\PublicAPI;

use App\Common\Kernel\Http\Controllers\AbstractAppController;

/**
 * Class PublicAPIController
 * @package App\Common\PublicAPI
 */
abstract class PublicAPIController extends AbstractAppController
{
    /** @var array Query logging: Ignore following params from request body */
    public const QL_IGNORE_REQ_PARAMS = [];
    /** @var bool Query logging: Save request params? */
    public const QL_SAVE_REQ_PARAMS = true;
    /** @var bool Query logging: Save response body? */
    public const QL_SAVE_RES_BODY = true;
}
