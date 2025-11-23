<?php

namespace App\Smtp\Command\CreateMailbox;

use App\Smtp\Entity\EmailAddress;
use App\Smtp\Repository\EmailAddressRepository;
use App\Smtp\Service\EmailAddressGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class Handler
{
    const int ATTEMPTS_LIMIT = 10;

    public function __construct(
        private EmailAddressGenerator $generator,
        private EmailAddressRepository $repository,
        #[Autowire('%smtp.mailbox_ttl_hours%')] private int $ttl,
    ) {
    }

    public function __invoke(Command $command): string
    {
        for ($i = 0; $i <= self::ATTEMPTS_LIMIT; $i++) {
            $addr = $this->generator->generate();

            $record = $this->repository->findByAddr($addr);
            if (\is_null($record)) {
                $this->repository->save(new EmailAddress($addr, $this->ttl));
                return $addr;
            }
        }
        throw new \LogicException('Can not generate email, try again');
    }
}
