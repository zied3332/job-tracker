<?php

namespace App\Controller;

use App\Entity\Job;
use App\Form\JobTypeForm;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/jobs')]
class JobController extends AbstractController
{
    #[Route('/', name: 'job_index')]
    public function index(JobRepository $jobRepository): Response
    {
        $jobs = $jobRepository->findAll();

        return $this->render('job/index.html.twig', [
            'jobs' => $jobs,
            
        ]);
    }

#[Route('/new', name: 'job_new')]
public function new(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
{
    $job = new Job();
    $form = $this->createForm(JobTypeForm::class, $job);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $company = $job->getCompany();
        $companyName = $company->getName();
        $category = $job->getCategory();
        $categoryLetter = strtoupper(substr($category, 0, 1));

        $jobNumber = '';

        if (strtolower($companyName) === 'coastal interiors') {
            $lastJob = $em->getRepository(Job::class)->createQueryBuilder('j')
                ->where('j.company = :company')
                ->andWhere('j.jobNumber LIKE :prefix')
                ->setParameter('company', $company)
                ->setParameter('prefix', 'CI-%')
                ->orderBy('j.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $nextNumber = 201;
            if ($lastJob) {
                $parts = explode('-', $lastJob->getJobNumber());
                $lastNum = isset($parts[1]) ? (int) preg_replace('/\D/', '', $parts[1]) : 0;
                $nextNumber = $lastNum + 1;
            }

            $jobNumber = 'CI-' . $nextNumber;
        } else {
            $lastJob = $em->getRepository(Job::class)->createQueryBuilder('j')
                ->where('j.company = :company')
                ->andWhere('j.jobNumber LIKE :prefix')
                ->setParameter('company', $company)
                ->setParameter('prefix', 'PR%')
                ->orderBy('j.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $nextNumber = 1050;
            if ($lastJob) {
                $parts = explode('-', $lastJob->getJobNumber());
                $lastNum = isset($parts[1]) ? (int) preg_replace('/\D/', '', $parts[1]) : 0;
                $nextNumber = $lastNum + 1;
            }

            $yearSuffix = date('y');
            $jobNumber = sprintf('PR%s-%04d%s', $yearSuffix, $nextNumber, $categoryLetter);
        }

        $job->setJobNumber($jobNumber);
        $job->setCreatedAt(new \DateTimeImmutable());

        $em->persist($job);
        $em->flush();

        $email = (new Email())
            ->from('ziedalimi2244@gmail.com')
            ->to('ziedalimi2244@gmail.com')
            ->subject('New Job Created')
            ->html('<p>A new job has been created:</p>
                <ul>
                    <li><strong>Job Number:</strong> ' . $job->getJobNumber() . '</li>
                    <li><strong>Company:</strong> ' . $job->getCompany()->getName() . '</li>
                    <li><strong>Category:</strong> ' . $job->getCategory() . '</li>
                </ul>');

        $mailer->send($email);

        $this->addFlash('success', 'Job created successfully and email sent.');
        return $this->redirectToRoute('job_dashboard');
    }

    return $this->render('job/new.html.twig', [
        'form' => $form->createView(),
    ]);
}


#[Route('/dashboard', name: 'job_dashboard')]
public function dashboard(JobRepository $jobRepository, CompanyRepository $companyRepository, Request $request): Response
{
    $page = $request->query->getInt('page', 1);
    $limit = 25;
    $offset = ($page - 1) * $limit;

    $filterType = $request->query->get('filterType');
    $filterValue = $request->query->get('filterValue');
    $selectedCompany = $request->query->get('company');
    $isNewest = $request->query->getBoolean('newest', false);

    $sort = $request->query->get('sort', 'createdAt');
    $direction = strtolower($request->query->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

    $allowedSortFields = ['jobNumber', 'claimNumber', 'address', 'city', 'state', 'status', 'category', 'projectManager', 'name', 'customer', 'createdAt'];
    if (!in_array($sort, $allowedSortFields, true)) {
        $sort = 'createdAt';
    }

    // Base QueryBuilder
    $qb = $jobRepository->createQueryBuilder('j')
        ->leftJoin('j.company', 'c');

    $column = null;

    // Apply filters only if not showing "Newest"
    if (!$isNewest) {
        if ($filterType && $filterValue) {
            $column = match ($filterType) {
                'jobNumber' => 'j.jobNumber',
                'city' => 'j.city',
                'manager' => 'j.projectManager',
                'status' => 'j.status',
                'category' => 'j.category',
                'createdAt' => 'j.createdAt',
                'company' => 'c.name',
                default => null,
            };

            if ($column) {
                $qb->andWhere($qb->expr()->like($column, ':filter'))
                   ->setParameter('filter', '%' . $filterValue . '%');
            }
        }

        if ($selectedCompany) {
            $qb->andWhere('c.name = :companyName')
               ->setParameter('companyName', $selectedCompany);
        }

        $qb->orderBy('j.' . $sort, $direction)
           ->setFirstResult($offset)
           ->setMaxResults($limit);
    } else {
        // If newest flag is enabled, skip filters and pagination
        $qb->orderBy('j.createdAt', 'DESC')
           ->setMaxResults(10);
    }

    $jobs = $qb->getQuery()->getResult();

    // Total pages (only used if not showing "newest")
    if (!$isNewest) {
        $countQb = $jobRepository->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->leftJoin('j.company', 'c');

        if ($filterType && $filterValue && $column) {
            $countQb->andWhere($countQb->expr()->like($column, ':filter'))
                    ->setParameter('filter', '%' . $filterValue . '%');
        }

        if ($selectedCompany) {
            $countQb->andWhere('c.name = :companyName')
                    ->setParameter('companyName', $selectedCompany);
        }

        try {
            $total = (int) $countQb->getQuery()->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException) {
            $total = 0;
        }

        $totalPages = $total > 0 ? ceil($total / $limit) : 1;
    } else {
        $totalPages = 1;
    }

    // Fetch company list for dropdown
    $companies = $companyRepository->findAll();

    return $this->render('job/dashboard.html.twig', [
        'jobs' => $jobs,
        'page' => $page,
        'totalPages' => $totalPages,
        'filterType' => $filterType,
        'filterValue' => $filterValue,
        'selectedCompany' => $selectedCompany,
        'companies' => $companies,
        'isNewest' => $isNewest,
        'sort' => $sort,
        'direction' => $direction,
    ]);
}



#[Route('/jobs/load', name: 'job_load', methods: ['GET'])]
public function loadJobs(Request $request, JobRepository $jobRepository, EntityManagerInterface $em): JsonResponse
{
    $page = max(1, (int) $request->query->get('page', 1));
    $limit = 25;
    $offset = ($page - 1) * $limit;

    $status = $request->query->get('status', 'open');
    $search = $request->query->get('search');
    $searchType = $request->query->get('searchType', 'jobNumber');
    $company = $request->query->get('company');

    $allowedSearchFields = [
        'jobNumber', 'claimNumber', 'city', 'state', 'customer', 'projectManager', 'category'
    ];

    $qb = $jobRepository->createQueryBuilder('j')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->orderBy('j.createdAt', 'DESC');

    if ($status === 'open') {
        $qb->andWhere('j.status = :status')
           ->setParameter('status', 'Open');
    }

    if ($search && $searchType && in_array($searchType, $allowedSearchFields)) {
        $qb->andWhere("LOWER(j.$searchType) LIKE :search")
           ->setParameter('search', '%' . strtolower($search) . '%');
    }

    if ($company) {
        $qb->andWhere('LOWER(j.company) LIKE :company')
           ->setParameter('company', '%' . strtolower($company) . '%');
    }

    $jobs = $qb->getQuery()->getResult();
    $companies = $em->getRepository(Company::class)->findAll();

    $html = $this->renderView('job/_table_rows.html.twig', [
        'jobs' => $jobs,
        'companies' => $companies,
        'page' => $page,
    ]);

    $hasMore = count($jobs) === $limit;

    return new JsonResponse([
        'html' => $html,
        'hasMore' => $hasMore,
        'nextPage' => $page + 1,
    ]);
}
#[Route('/ajax-edit/{id}', name: 'job_ajax_edit', methods: ['POST'])]
public function ajaxEdit(Request $request, Job $job, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
{
    if (!$request->isXmlHttpRequest()) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
    }

    $data = json_decode($request->getContent(), true);

    // Manually update the fields
    $job->setClaimNumber($data['claimNumber'] ?? '');
    $job->setAddress($data['address'] ?? '');
    $job->setCity($data['city'] ?? '');
    $job->setState($data['state'] ?? '');
    $job->setStatus($data['status'] ?? '');
    $job->setCategory($data['category'] ?? '');
    $job->setProjectManager($data['projectManager'] ?? '');
    $job->setName($data['name'] ?? '');
    $job->setCustomer($data['customer'] ?? '');
    $job->setDescription($data['description'] ?? '');

    // Handle company
    if (isset($data['company'])) {
        $company = $em->getRepository(Company::class)->find($data['company']);
        if ($company) {
            $job->setCompany($company);
        }
    }

    // Validate
    $errors = $validator->validate($job);
    if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
            $property = $error->getPropertyPath();
            $errorMessages[$property] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'errors' => $errorMessages]);
    }

    $em->flush();

    return new JsonResponse(['success' => true]);
}



    #[Route('/{id}/delete', name: 'job_delete', methods: ['POST'])]
    public function delete(Request $request, Job $job, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $job->getId(), $request->request->get('_token'))) {
            $em->remove($job);
            $em->flush();
            $this->addFlash('success', 'Job deleted successfully.');
        }

        return $this->redirectToRoute('job_dashboard');
    }

    #[Route('/export', name: 'job_export')]
    public function exportJobs(JobRepository $jobRepository): Response
    {
        $jobs = $jobRepository->findAll();

        $csv = "ID,Job Number,Claim Number,Address,City,State,Status,Category,Manager,Company,Created At\n";

        foreach ($jobs as $job) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $job->getId(),
                $job->getJobNumber(),
                $job->getClaimNumber(),
                $job->getAddress(),
                $job->getCity(),
                $job->getState(),
                $job->getStatus(),
                $job->getCategory(),
                $job->getProjectManager(),
                $job->getCompany()->getName(),
                $job->getCreatedAt()?->format('Y-m-d H:i')
            );
        }
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="jobs.csv"',
        ]);
    }

    #[Route('/export/excel', name: 'job_export_excel')]
    public function exportToExcel(JobRepository $jobRepository): Response
    {
        $jobs = $jobRepository->findAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Jobs");

        $sheet->fromArray([
            'ID', 'Job #', 'Claim #', 'Address', 'City', 'State', 'Status', 'Category', 'Manager', 'Company', 'Created At'
        ], null, 'A1');

        $row = 2;
        foreach ($jobs as $job) {
            $sheet->fromArray([
                $job->getId(),
                $job->getJobNumber(),
                $job->getClaimNumber(),
                $job->getAddress(),
                $job->getCity(),
                $job->getState(),
                $job->getStatus(),
                $job->getCategory(),
                $job->getProjectManager(),
                $job->getCompany()?->getName(),
                $job->getCreatedAt()?->format('Y-m-d H:i'),
            ], null, 'A' . $row++);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        return $this->file($tempFile, 'jobs_export.xlsx', ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/export/pdf', name: 'job_export_pdf')]
    public function exportToPdf(JobRepository $jobRepository): Response
    {
        $jobs = $jobRepository->findAll();

        $html = '<h2 style="text-align:center;">Job List</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
        $html .= '<thead><tr>
                    <th>ID</th><th>Job #</th><th>Claim #</th><th>Address</th>
                    <th>City</th><th>State</th><th>Status</th><th>Category</th>
                    <th>Manager</th><th>Company</th><th>Created At</th>
                  </tr></thead><tbody>';

        foreach ($jobs as $job) {
            $html .= '<tr>';
            $html .= '<td>' . $job->getId() . '</td>';
            $html .= '<td>' . $job->getJobNumber() . '</td>';
            $html .= '<td>' . $job->getClaimNumber() . '</td>';
            $html .= '<td>' . $job->getAddress() . '</td>';
            $html .= '<td>' . $job->getCity() . '</td>';
            $html .= '<td>' . $job->getState() . '</td>';
            $html .= '<td>' . $job->getStatus() . '</td>';
            $html .= '<td>' . $job->getCategory() . '</td>';
            $html .= '<td>' . $job->getProjectManager() . '</td>';
            $html .= '<td>' . ($job->getCompany()?->getName() ?? '') . '</td>';
            $html .= '<td>' . ($job->getCreatedAt()?->format('Y-m-d H:i') ?? '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="jobs_export.pdf"',
            ]
        );
    }



#[Route('/jobs/{id}', name: 'job_view')]
public function view(Job $job): Response
{
    return $this->render('job/view.html.twig', [
        'job' => $job,
    ]);
}

}
