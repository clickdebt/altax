<?php
namespace Altax\Command\Builtin;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Server;

/**
 * Roles Command
 */
class RolesCommand extends \Symfony\Component\Console\Command\Command
{
    protected function configure()
    {
        $this
            ->setName('roles')
            ->setDescription('Displays roles')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'To output list in other formats (txt|txt-no-header|json)',
                'txt'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $roles = Server::getRoles();

        $format = $input->getOption('format');
        if ('txt' === $format || 'txt-no-header' === $format) {
            $table = new Table($output);
            $style = new TableStyle();
            $style->setHorizontalBorderChar('')
                ->setVerticalBorderChar('')
                ->setCrossingChar('')
                ->setCellRowContentFormat("%s    ")
                ;
            $table->setStyle($style);

            if ($roles) {
                if ('txt-no-header' !== $format ) {
                    $table->setHeaders(array('name', 'nodes'));
                }

                foreach ($roles as $key => $role) {

                    $table->addRow(array(
                        $key,
                        trim(implode(',', $role->nodes)),
                    ));
                }
                $table->render($output);
            } else {
                $output->writeln('There are not any roles.');
            }
        } elseif ('json' === $format) {
            $data = array();
            if ($roles) {
                foreach ($roles as $key => $role) {
                    $nodeNames = array();
                    foreach ($role->nodes as $nodeName => $node) {
                        $nodeNames[] = $nodeName;
                    }
                    $data[$key] = array(
                        'nodes' => $nodeNames
                    );
                }
            }
            $output->writeln(json_encode($data));
        } else {
            throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $format));
        }
    }
}
