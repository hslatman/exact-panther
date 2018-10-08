<?php
/**
 * Author: Herman Slatman
 * Date: 08/10/2018
 * Time: 19:52
 */

namespace App\Command;

use App\Service\ExactService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckHoursCommand extends Command
{
    protected static $defaultName = 'exact:check-hours';

    /** @var ExactService $exact_service */
    private $exact_service;

    public function __construct(ExactService $exact_service)
    {
        $this->exact_service = $exact_service;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Checks hours in Exact by automating browser')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->exact_service->go();
        
        $table = new Table($output);
        $table
            ->setHeaders(array('Naam', 'Ingevuld', 'Nagekeken', 'Manager'))
        ;
        foreach ($result as $result_row) {
            list($name, $is_ingevuld, $is_nagekeken, $manager) = $result_row;
            $is_ingevuld_text = $is_ingevuld ? "<fg=green;>OK</>" : "<fg=red;>X</>";
            $is_nagekeken_text = $is_ingevuld && $is_nagekeken ? "<fg=green;>OK</>" : "<fg=red;>X</>";
            $manager = "";
            $table->addRow([$name, $is_ingevuld_text, $is_nagekeken_text, $manager]);
        }

        $table->render();
    }
}