<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgot(Request $request, EntityManagerInterface $em, MailerInterface $mailer, SessionInterface $session): Response
    {
        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');

            // Debug — shows if form submits
            if (!$username) {
                $this->addFlash('danger', 'No username entered.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);

            if (!$user) {
                $this->addFlash('danger', 'No user found with that username.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Generate 6-digit code
            $code = random_int(100000, 999999);

            // Store in session
            $session->set('reset_code', $code);
            $session->set('reset_user_id', $user->getId());

            // Send email
            $email = (new Email())
                ->from('noreply@jobtracker.com')
                ->to($user->getUsername()) // username is the email
                ->subject('Password Reset Code')
                ->text("Your reset code is: $code");

            try {
                $mailer->send($email);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Error sending email: ' . $e->getMessage());
                return $this->redirectToRoute('app_forgot_password');
            }

            $this->addFlash('success', 'Reset code sent. Check your email.');
            return $this->redirectToRoute('app_verify_code');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/verify-code', name: 'app_verify_code')]
    public function verifyCode(Request $request, SessionInterface $session): Response
    {
        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            if ($code == $session->get('reset_code')) {
                return $this->redirectToRoute('app_reset_password');
            } else {
                $this->addFlash('danger', 'Invalid code.');
            }
        }

        return $this->render('security/verify_code.html.twig');
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function resetPassword(Request $request, EntityManagerInterface $em, SessionInterface $session, UserPasswordHasherInterface $hasher): Response
    {
        $userId = $session->get('reset_user_id');
        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $this->addFlash('danger', 'Something went wrong.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $hashed = $hasher->hashPassword($user, $password);
            $user->setPassword($hashed);
            $em->flush();

            $session->remove('reset_code');
            $session->remove('reset_user_id');

            $this->addFlash('success', 'Password updated successfully.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig');
    }
}
