<?php
/**
 * Created by PhpStorm.
 * User: andreas
 * Date: 10.05.17
 * Time: 01:57
 */

namespace M4bTool\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChapterCommand extends Command
{
    protected function configure()
    {
        $this->setName('chapter')
            // the short description shown while running "php bin/console list"
            ->setDescription('Adds chapters to m4b file')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Can add Chapters to m4b files via different types of inputs')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln([
            'User Creator',
            '============',
            '',
        ]);

        // outputs a message followed by a "\n"
        $output->writeln('Whoa!');

        // outputs a message without adding a "\n" at the end of the line
        $output->write('You are about to ');
        $output->write('create a user.');
    }
}