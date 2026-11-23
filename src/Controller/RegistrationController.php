<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($existingUser) {
                return $this->registrationRender('This email is already registered.');
            }

            $firstName = trim((string) $request->request->get('firstName'));
            $lastName = trim((string) $request->request->get('lastName'));

            $ageRaw = $request->request->get('age');
            $age = is_numeric($ageRaw) ? (int) $ageRaw : 0;
            if ($age <= 0) {
                return $this->registrationRender('Age must be a positive number.');
            }

            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');

            if (\strlen((string) $password) < 6) {
                return $this->registrationRender('Password must be at least 6 characters.');
            }

            if ($password !== $confirmPassword) {
                return $this->registrationRender('Passwords do not match.');
            }

            $weightUnit = (string) $request->request->get('weightUnit', 'kg');
            if (!in_array($weightUnit, ['kg', 'lbs'], true)) {
                return $this->registrationRender('Invalid weight unit.');
            }

            //is numeric checks whether the value is a number
            $weightRaw = $request->request->get('weight');
            $weight = is_numeric($weightRaw) ? (float) $weightRaw : 0.0;

            $heightUnit = (string) $request->request->get('heightUnit', 'cm');
            if (!in_array($heightUnit, ['cm', 'ft'], true)) {
                return $this->registrationRender('Invalid height unit.');
            }

            // height is only stored in cm

            if ($heightUnit === 'ft') {
                $ftRaw = $request->request->get('heightFt');
                $inRaw = $request->request->get('heightIn');
                $ft = is_numeric($ftRaw) ? (float) $ftRaw : 0.0;
                $in = is_numeric($inRaw) ? (float) $inRaw : 0.0;
                if ($in < 0) {
                    $in = 0.0;
                }
                if ($in >= 12) {
                    $in = 11.0;
                }
                $height = ($ft * 30.48) + ($in * 2.54);
            } else {
                $heightRaw = $request->request->get('height');
                $height = is_numeric($heightRaw) ? (float) $heightRaw : 0.0;
            }

            if ($weight <= 0) {
                return $this->registrationRender('Weight must be a positive number.');
            }
            if ($height <= 0) {
                return $this->registrationRender('Height must be a positive number.');
            }

            $gender = (string) $request->request->get('gender');
            if (!in_array($gender, ['male', 'female', 'other'], true)) {
                return $this->registrationRender('Please choose a valid gender.');
            }

            if ($weightUnit === 'lbs') {
                $weight *= 0.453592;
            }

            $user = new User();
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setEmail($email);
            $user->setPassword(password_hash((string) $password, PASSWORD_BCRYPT));
            $user->setAge($age);
            $user->setWeight($weight);
            $user->setHeight($height);
            $user->setWeightUnit($weightUnit);
            $user->setHeightUnit($heightUnit);
            $user->setGender($gender);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->registrationRender();
    }

    private function registrationRender(?string $error = null): Response
    {
        return $this->render('registration/index.html.twig', [
            'error' => $error,
        ]);
    }
}
