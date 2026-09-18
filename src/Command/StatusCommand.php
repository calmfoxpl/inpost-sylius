<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Command;

use Calmfox\InPostBundle\Api\ShipXClients;
use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\CalmfoxInPostBundle;
use Calmfox\InPostBundle\Checkout\Geowidget;
use Calmfox\InPostBundle\Shipping\CredentialsProvider;
use Calmfox\InPostBundle\Shipping\Environment;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Stan integracji z wiersza poleceń: tryb, skąd pochodzą dane obu kont, czy ShipX je przyjmuje
 * i czy konto ma usługi, których używają metody dostawy. Do kontroli po wdrożeniu i do zgłoszeń
 * błędów — nie wypisuje żadnego tokenu, tylko ich źródło.
 */
#[AsCommand(name: 'calmfox:inpost:status', description: 'Pokazuje tryb, źródła danych kont i stan połączenia z InPost (bez sekretów).')]
final class StatusCommand extends Command
{
    public function __construct(
        private readonly Environment $environment,
        private readonly CredentialsProvider $credentials,
        private readonly ShipXClients $clients,
        private readonly Geowidget $geowidget,
        private readonly MethodMap $methodMap,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('offline', null, InputOption::VALUE_NONE, 'Nie łącz się z InPost — pokaż tylko konfigurację');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sandboxMode = $this->environment->isSandbox();
        $io->title(sprintf('calmfox/inpost-sylius %s — tryb: %s', CalmfoxInPostBundle::VERSION, $sandboxMode ? 'SANDBOX' : 'produkcja'));

        $needed = array_values(array_unique(array_values($this->methodMap->all())));
        $rows = [];
        $activeOk = true;

        foreach ([false, true] as $sandbox) {
            $credentials = $this->credentials->get($sandbox);
            $connection = 'pominięto';

            if (!$credentials->isComplete()) {
                $connection = 'brak danych konta';
                $ok = false;
            } elseif ($input->getOption('offline')) {
                $ok = true;
            } else {
                try {
                    $organization = $this->clients->get($sandbox)->getOrganization();
                    $services = \is_array($organization['services'] ?? null) ? $organization['services'] : [];
                    $missing = array_diff($needed, $services);
                    $ok = [] === $missing;
                    $connection = $ok
                        ? sprintf('OK — %s', \is_string($organization['name'] ?? null) ? $organization['name'] : 'organizacja '.$credentials->organizationId)
                        : 'konto bez usług: '.implode(', ', $missing);
                } catch (ShipXException $e) {
                    $ok = false;
                    $connection = $e->getOperatorMessage();
                }
            }

            if ($sandbox === $sandboxMode && !$ok) {
                $activeOk = false;
            }

            $rows[] = [
                ($sandbox ? 'sandbox' : 'produkcja').($sandbox === $sandboxMode ? ' (aktywny)' : ''),
                $credentials->tokenSource,
                '' === $credentials->organizationId ? '—' : $credentials->organizationId.' ('.$credentials->organizationSource.')',
                $this->geowidget->token($sandbox)['source'],
                $connection,
            ];
        }

        $io->table(['Konto', 'Token ShipX', 'ID organizacji', 'Token mapy', 'Połączenie'], $rows);
        $io->writeln('Metody dostawy: '.implode(', ', array_map(static fn (string $code, string $service): string => $code.' → '.$service, array_keys($this->methodMap->all()), $this->methodMap->all())));
        $io->newLine();

        if (!$activeOk) {
            $io->error('Aktywny tryb nie jest gotowy do nadawania przesyłek.');

            return Command::FAILURE;
        }
        $io->success('Aktywny tryb jest gotowy.');

        return Command::SUCCESS;
    }
}
