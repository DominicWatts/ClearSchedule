<?php
/**
 * Copyright © 2020 All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ClearSchedule\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;

class Clear extends Command
{

    const DELETE_DAYS = 1;
    const LIMIT = 1000000;
    
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    private $report = [];

    /**
     * @var null
     */
    private $startTime = null;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->connection = $resource->getConnection();
        $this->resource = $resource;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->input = $input;
        $this->output = $output;

        $this->state->setAreaCode(Area::AREA_GLOBAL);

        $this->output->writeln((string) __(
            '[%1] Start',
            $this->dateTime->gmtDate()
        ));

        $this->output->writeln((string) __(
            '[%1] Cleaning entries',
            $this->dateTime->gmtDate()
        ));

        $this->tableName = $this->getTableName();
        $this->startTime = time();
        $this->report = [];

        $select = $this->connection
            ->select()
            ->from($this->tableName)
            ->where('scheduled_at < DATE_SUB(NOW(), INTERVAL ? DAY)', self::DELETE_DAYS)
            ->limit(self::LIMIT);

        $this->doDeleteFromSelect($select);

        $this->output->writeln((string) __(
            '[%1] Result : Duration %2 - Count %3 ',
            $this->dateTime->gmtDate(),
            $this->report['duration'] ?? 'NaN',
            $this->report['count'] ?? 'NaN'
        ));

        $this->output->writeln((string) __(
            '[%1] Finish',
            $this->dateTime->gmtDate()
        ));
    }

    /**
     * Delete from select
     * @param string $select
     * @return array
     */
    public function doDeleteFromSelect($select)
    {
        try {
            $this->report['duration'] = time() - $this->startTime;
            // deliberate empty second argument
            $query = $this->connection->deleteFromSelect($select, []);
            $statement = $this->connection->query($query);
            $this->report['count'] = $statement->rowCount();
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return $this->report;
    }

    /**
     * Get cron schedule table name
     * @return string
     */
    public function getTableName()
    {
        return $this->resource->getTableName('cron_schedule');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("xigen:clearschedule:clear");
        $this->setDescription("Clear cron schedule");
        parent::configure();
    }
}
