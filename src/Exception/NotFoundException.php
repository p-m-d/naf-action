<?php
namespace Naf\Action\Exception;

class NotFoundException extends ActionException {

	protected $status = 404;

}