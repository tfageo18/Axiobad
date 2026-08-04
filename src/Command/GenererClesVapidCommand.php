<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:push:generer-cles-vapid',
    description: 'Génère une paire de clés VAPID pour les notifications push (Web Push)',
)]
class GenererClesVapidCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cles = VAPID::createVapidKeys();

        $io->title('Nouvelle paire de clés VAPID');
        $io->text('Copiez ces valeurs dans .env.local (ou .env.prod.local en production) :');
        $io->newLine();
        $io->writeln('VAPID_PUBLIC_KEY='.$cles['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY='.$cles['privateKey']);
        $io->newLine();
        $io->warning('La clé privée doit rester secrète. Ne la commitez jamais dans le dépôt.');

        return Command::SUCCESS;
    }
}
