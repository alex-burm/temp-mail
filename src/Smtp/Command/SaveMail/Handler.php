<?php

namespace App\Smtp\Command\SaveMail;

final class SaveMailHandler
{
    public function __invoke(Command $cmd): void
    {
        \file_put_contents(\time() . '.eml', $cmd->data);
        //$this->repository->save($mail);
    }
}
