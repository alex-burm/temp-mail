<?php

namespace App\Smtp\Command\Checker;

use App\Smtp\Entity\EmailMessage;
use App\Smtp\Repository\EmailMessageRepository;
use App\Smtp\Service\DataParser;
use App\Smtp\Service\DkimChecker\DkimChecker;
use App\Smtp\Service\DmarcChecker\DmarcChecker;
use App\Smtp\Service\SpfChecker\SpfChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class Handler
{
    public function __construct(
        protected EmailMessageRepository $repository,
        protected LoggerInterface        $logger,
        protected SpfChecker             $spfChecker,
        protected DataParser             $dataParser,
        protected DkimChecker            $dkimChecker,
        protected DmarcChecker           $dmarcChecker,
    ) {
    }

    public function __invoke(Command $cmd): void
    {
        /** @var EmailMessage $message */
        $message = $this->repository->find($cmd->id);

        $this->checkSpf($message, $cmd);
        $this->checkDkim($message, $cmd);
        $this->checkDmarc($message, $cmd);
    }

    protected function checkSpf(EmailMessage $message, Command $cmd): void
    {
        if (false === \is_null($message->spf)) {
            return;
        }

        $this->logger->info('SpfChecker run');
        $spfResult = $this->spfChecker->check($cmd->ip, $cmd->domain);
        $this->logger->info('SpfChecker result', ['result' => $spfResult]);

        $message->spf = $spfResult;
        $this->repository->save($message);
    }

    protected function checkDkim(EmailMessage $message, Command $cmd): void
    {
        if (false === \is_null($message->dkim)) {
            return;
        }

        $this->logger->info('DkimChecker run');
        $dkimResult = $this->dkimChecker->check($message->data);
        $this->logger->info('DkimChecker result', ['result' => $dkimResult]);

        $message->dkim = $dkimResult;
        $this->repository->save($message);
    }

    protected function checkDmarc(EmailMessage $message, Command $cmd): void
    {
        if (false === \is_null($message->dmarc)) {
            return;
        }

        $this->logger->info('DmarcChecker run');
        $dmarcResult = $this->dmarcChecker->check($cmd->domain, $message->spf, $message->dkim);
        $this->logger->info('DmarcChecker result', ['result' => $dmarcResult]);

        $message->dmarc = $dmarcResult;
        $this->repository->save($message);
    }
}
