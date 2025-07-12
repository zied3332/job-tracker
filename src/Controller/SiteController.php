<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Company;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;

class SiteController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('site/home.html.twig');
    }

    #[Route('/form', name: 'app_form')]
    public function form(): Response
    {
        return $this->render('job/new.html.twig');
    }

  #[Route('/dashboard', name: 'job_dashboard1')]
    public function dashboard(JobRepository $jobRepository, EntityManagerInterface $em): Response
    {
        $jobs = $jobRepository->findAll();
        $companies = $em->getRepository(Company::class)->findAll();

        return $this->render('job/dashboard.html.twig', [
            'jobs' => $jobs,
            'companies' => $companies,
            'page' => 1,
            'totalPages' => 1
        ]);
    }

  
}
