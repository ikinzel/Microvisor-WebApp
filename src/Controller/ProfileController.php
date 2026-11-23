<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserDailyGoal;
use App\Service\DailyRequirementsCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        DailyRequirementsCalculator $calculator,
    ): Response {

        //session is checked. user may have to log in again if no session is found.
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        //user is found in the database. if not, the session is cleared and the user is redirected to the login page.
        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');
            return $this->redirectToRoute('app_login');
        }

        //error and success messages set to null by deafult 
        $error = null;
        $success = null;

        //the forms are submitted via POST request to the server with an action parameter set to profile or goals.
        // the handle methods return success or error messages.
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('_action', 'profile');

            if ($action === 'profile') {
                [$error, $success] = $this->handleSave($request, $user, $entityManager, $session);
            } elseif ($action === 'goals') {
                [$error, $success] = $this->handleGoalsSave($request, $user, $entityManager, $calculator);
            }
        }

        $recs = $calculator->calculate($user);

        return $this->render('profile/index.html.twig', [
            'userName' => $session->get('user_name'),
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'recs' => $recs,
        ]);

    }

     //handles the profile form submission 

    private function handleSave(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
    ): array {
        //from the form, the data is extracted and trimmed.
        $firstName = trim((string) $request->request->get('firstName', ''));
        $lastName = trim((string) $request->request->get('lastName', ''));
        $email = trim((string) $request->request->get('email', ''));
        $gender = (string) $request->request->get('gender', '');
        $heightUnit = (string) $request->request->get('heightUnit', 'cm');

        $ageRaw = $request->request->get('age');
        $age = is_numeric($ageRaw) ? (int) $ageRaw : 0;


        $weightRaw = $request->request->get('weight');
        $weight = is_numeric($weightRaw) ? (float) $weightRaw : 0.0;
        $weightUnit = (string) $request->request->get('weightUnit', 'kg');

        if ($heightUnit === 'ft') {
            $ftRaw = $request->request->get('heightFt');
            $inRaw = $request->request->get('heightIn');
            $ft = is_numeric($ftRaw) ? (float) $ftRaw : 0.0;
            $in = is_numeric($inRaw) ? (float) $inRaw : 0.0;
            if ($in < 0) { $in = 0.0; }
            if ($in >= 12) { $in = 11.0; }
            // Convert feet+inches to cm
            $height = ($ft * 30.48) + ($in * 2.54);
        } else {
            $heightRaw = $request->request->get('height');
            $height = is_numeric($heightRaw) ? (float) $heightRaw : 0.0;
        }

        //errors array is created to store any validation errors.
        $errors = [];
        //validation checks are performed on the data.
        if ($firstName === '') {
            $errors[] = 'First name is required.';
        }
        if ($lastName === '') {
            $errors[] = 'Last name is required.';
        }
        if ($email === '' || !str_contains($email, '@')) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($age <= 0 || $age > 120) {
            $errors[] = 'Age must be between 1 and 120.';
        }
        if ($weight <= 0) {
            $errors[] = 'Weight must be a positive number.';
        }
        if ($height <= 0) {
            $errors[] = 'Height must be a positive number.';
        }
        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $errors[] = 'Please choose a valid gender.';
        }
        if (!in_array($weightUnit, ['kg', 'lbs'], true)) {
            $errors[] = 'Invalid weight unit.';
        }
        if (!in_array($heightUnit, ['cm', 'ft'], true)) {
            $errors[] = 'Invalid height unit.';
        }

        if ($errors !== []) {
            return [implode(' ', $errors), null];
        }

        // Normalize to stored units: kg and cm.
        if ($weightUnit === 'lbs') {
            $weight *= 0.453592;
        }

        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setAge($age);
        $user->setWeight($weight);
        $user->setHeight($height);
        $user->setWeightUnit($weightUnit);
        $user->setHeightUnit($heightUnit);
        $user->setGender($gender);

        $entityManager->flush();

        // Keep the sidebar welcome name in sync. 
        $session->set('user_name', $user->getFirstName());

        return [null, 'Profile saved.'];
    }

    
    // AI generated code for the goals form submission. 

    private function handleGoalsSave(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        DailyRequirementsCalculator $calculator,
    ): array {
        // button in goals form. On submit, the browser sends a POST request to the server with it set to 1.
        if ($request->request->get('resetGoals') === '1') {
            foreach ($user->getDailyGoals() as $goal) {
                $entityManager->remove($goal);
            }
            $entityManager->flush();

            return [null, 'Daily goals reset to calculated recommendations.'];
        }
        //reads all POST form fields and stores them in the submitted array.
        $submitted = $request->request->all();
        $macroInput = isset($submitted['macros']) && \is_array($submitted['macros']) ? $submitted['macros'] : [];
        $microInput = isset($submitted['micronutrients']) && \is_array($submitted['micronutrients']) ? $submitted['micronutrients'] : [];
        $defaults = $calculator->calculateDefaults($user);

        $errors = [];
        $macros = [];

        foreach (['carbs_g', 'fat_g'] as $rangeKey) {
            $minRaw = $macroInput[$rangeKey]['min'] ?? null;
            $maxRaw = $macroInput[$rangeKey]['max'] ?? null;
            $min = is_numeric($minRaw) ? (float) $minRaw : null;
            $max = is_numeric($maxRaw) ? (float) $maxRaw : null;

            if ($min === null || $max === null || $min < 0 || $max < 0) {
                $errors[] = 'Macro ranges must be non-negative numbers.';
                continue;
            }
            if ($min > $max) {
                $errors[] = 'Macro minimums cannot be greater than maximums.';
                continue;
            }

            $macros[$rangeKey] = [
                'min' => (int) round($min),
                'max' => (int) round($max),
            ];
        }

        foreach (['fiber_g', 'protein_g'] as $singleKey) {
            $raw = $macroInput[$singleKey] ?? null;
            $value = is_numeric($raw) ? (float) $raw : null;
            if ($value === null || $value < 0) {
                $errors[] = 'Macro goals must be non-negative numbers.';
                continue;
            }
            $macros[$singleKey] = (int) round($value);
        }

        $micros = [];
        foreach ($defaults['micronutrients'] as $key => $item) {
            $raw = $microInput[$key] ?? null;
            $value = is_numeric($raw) ? (float) $raw : null;
            if ($value === null || $value < 0) {
                $errors[] = 'Micronutrient goals must be non-negative numbers.';
                continue;
            }
            $micros[$key] = $value;
        }

        if ($errors !== []) {
            return [implode(' ', array_unique($errors)), null];
        }

        foreach ($user->getDailyGoals() as $goal) {
            $entityManager->remove($goal);
        }
        $entityManager->flush();

        $macroGoals = [
            'carbs_g_min' => [(float) $macros['carbs_g']['min'], (float) $defaults['macros']['carbs_g']['min'], 'g'],
            'carbs_g_max' => [(float) $macros['carbs_g']['max'], (float) $defaults['macros']['carbs_g']['max'], 'g'],
            'fiber_g' => [(float) $macros['fiber_g'], (float) $defaults['macros']['fiber_g'], 'g'],
            'protein_g' => [(float) $macros['protein_g'], (float) $defaults['macros']['protein_g'], 'g'],
            'fat_g_min' => [(float) $macros['fat_g']['min'], (float) $defaults['macros']['fat_g']['min'], 'g'],
            'fat_g_max' => [(float) $macros['fat_g']['max'], (float) $defaults['macros']['fat_g']['max'], 'g'],
        ];

        foreach ($macroGoals as $key => [$value, $default, $unit]) {
            if ($this->goalDiffers($value, $default)) {
                $this->persistGoal($entityManager, $user, $key, $value, $unit);
            }
        }

        foreach ($defaults['micronutrients'] as $key => $item) {
            if ($this->goalDiffers($micros[$key], (float) $item['value'])) {
                $this->persistGoal($entityManager, $user, $key, $micros[$key], (string) $item['unit']);
            }
        }

        $entityManager->flush();

        return [null, 'Daily goals saved.'];
    }

    private function goalDiffers(float $value, float $default): bool
    {
        return abs($value - $default) > 0.0001;
    }
    
    //doctrine code to persist the goal to the database.
    private function persistGoal(
        EntityManagerInterface $entityManager,
        User $user,
        string $goalKey,
        float $value,
        string $unit,
    ): void {
        $goal = new UserDailyGoal();
        $goal->setUser($user);
        $goal->setGoalKey($goalKey);
        $goal->setValue($value);
        $goal->setUnit($unit);

        $entityManager->persist($goal);
    }
}
