<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Command;

use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\Core\ShipmentStatus;
use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Shipping\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Odświeża statusy i numery nadania przesyłek, które nie dotarły jeszcze do końca drogi.
 * Do crona (np. co 15 min); bez niego wszystko działa, tylko status odświeża się na żądanie operatora.
 */
#[AsCommand(name: 'calmfox:inpost:sync', description: 'Odświeża statusy przesyłek InPost, które są w drodze.')]
final class SyncCommand extends Command
{
    public function __construct(
        private readonly InPostShipmentRepository $repository,
        private readonly Dispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Ile przesyłek odświeżyć w jednym przebiegu', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $shipments = $this->repository->findAwaitingUpdate(ShipmentStatus::finalStatuses(), max(1, (int) $input->getOption('limit')));
        $failed = 0;

        foreach ($shipments as $shipment) {
            try {
                $this->dispatcher->refresh($shipment);
                $io->writeln(sprintf('%s  %s  %s', $shipment->getShipxId(), $shipment->getStatus() ?? '-', $shipment->getTrackingNumber() ?? '-'), OutputInterface::VERBOSITY_VERBOSE);
            } catch (ShipXException $e) {
                ++$failed;
                $io->warning(sprintf('%s: %s', $shipment->getShipxId(), $e->getOperatorMessage()));
            }
        }

        $io->success(sprintf('Odświeżono %d przesyłek, błędów: %d.', \count($shipments) - $failed, $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
