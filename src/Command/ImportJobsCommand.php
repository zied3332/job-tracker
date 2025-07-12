<?php

namespace App\Command;

use App\Entity\Job;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:jobs',
    description: 'Import jobs from CSV files',
)]
class ImportJobsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = __DIR__ . '/../../public/job_import';
        $logDirectory = __DIR__ . '/../../public/job_import_logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        $files = glob($directory . '/*.csv');

        foreach ($files as $file) {
            $io->section('Importing: ' . basename($file));
            $csv = Reader::createFromPath($file, 'r');
            $csv->setHeaderOffset(0);
            $records = iterator_to_array($csv->getRecords());

            $skippedJobs = [];

            foreach ($records as $record) {
                $jobNumber = $record['Job #'] ?? 'AUTO-' . uniqid();

                // Skip if job number is somehow still null (very rare case)
                if (!$jobNumber) {
                    $io->error("Missing Job Number.");
                    $record['Reason'] = 'Missing Job Number';
                    $skippedJobs[] = $record;
                    continue;
                }

                $projectManager = $record['Project Manager'] ?? 'Unassigned';

                $job = new Job();
                $job->setJobNumber((string) $jobNumber);
                $job->setName($record['Name'] ?? '');
                $job->setCustomer($record['Customer'] ?? '');
                $job->setAddress($record['Address'] ?? '');
                $job->setCity($record['City'] ?? '');
                $job->setState($record['State'] ?? '');
                $job->setClaimNumber($record['Claim #'] ?? '');
                $job->setStatus($record['Status'] ?? '');
                $job->setCategory($record['Category'] ?? '');
                $job->setProjectManager($projectManager);

                // Handle company relation
                $companyName = $record['Company'] ?? null;
                $company = null;
                if ($companyName) {
                    $company = $this->entityManager
                        ->getRepository(Company::class)
                        ->findOneBy(['name' => $companyName]);

                    if (!$company) {
                        $company = new Company();
                        $company->setName($companyName);
                        $this->entityManager->persist($company);
                    }
                }

                $job->setCompany($company);
                $job->setCreatedAt(new \DateTimeImmutable());

                $this->entityManager->persist($job);
            }

            $this->entityManager->flush();
            $io->success('Imported ' . count($records) . ' jobs from ' . basename($file));

            // If any skipped jobs occurred, export them (usually for missing job number)
            if (count($skippedJobs) > 0) {
                $logPath = $logDirectory . '/skipped_jobs_' . date('Ymd_His') . '.csv';
                $writer = Writer::createFromPath($logPath, 'w+');
                $writer->insertOne(array_keys($skippedJobs[0]));
                foreach ($skippedJobs as $row) {
                    $writer->insertOne($row);
                }

                $io->warning(count($skippedJobs) . ' jobs were skipped and saved to: ' . $logPath);
            }
        }

        return Command::SUCCESS;
    }
}
