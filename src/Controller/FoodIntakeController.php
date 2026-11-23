<?php

namespace App\Controller;

use App\Entity\FoodIntake;
use App\Entity\User;
use App\Enum\FoodIntakeUnit;
use App\Exception\ReceiptNotReadableException;
use App\Service\ReceiptImageBlurChecker;
use App\Service\ReceiptOcrExtractorService;
use App\Service\UsdaFoodDataCentralClient;
use App\Utils\UploadedMimeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


/*
 *FoodIntakeController handles food intake logging and management.
 *
 * Manages:
 * -Manual food entry ( user searches and logs by name)
 * - Receipt scanning (upload receipt image, OCR extracts items, user verifies)
 * - Food intake editing (modify amount, unit, date)
 * - Food intake deletion
 *
 * All actions require authentication. Uses sessions to store receipt preview
 * data during the multi-step receipt verification process.
 *
 */
final class FoodIntakeController extends AbstractController
{
    private const RECEIPT_PREVIEW_SESSION_KEY = 'food_receipt_preview';
    private const RECEIPT_TARGET_DATE_SESSION_KEY = 'food_receipt_target_date';
    private const RECEIPT_RETURN_VIEW_SESSION_KEY = 'food_receipt_return_view';
    private const RECEIPT_SHOW_WEEKENDS_SESSION_KEY = 'food_receipt_show_weekends';

    /** PHP default max_execution_time (30s) is too low for OpenAI vision + many USDA calls. */
    private const RECEIPT_MAX_EXECUTION_SECONDS = 180;

    /**
     * Handles food input
     *
     * GET requests redirect to home.
     * POST requests handle two actions:
     * - manual: User searches for food name and logs amount
     * - receipt: User uploads receipt image for OCR scanning
     *
     * Flow for manual:
     * 1. User enters food name
     * 2. Search USDA FoodData Central for matching food
     * 3. Take first match, log with user's amount and unit
     * 4. Save to database, show success message
     * 5. Redirect to dashboard
     *
     * Flow for receipt:
     * 1. User uploads receipt image (JPEG/PNG/WebP)
     * 2. Check image quality (reject if too blurry)
     * 3. Use OpenAI vision to extract food lines from receipt
     * 4. For each line, search USDA for matching food
     * 5. Store preview in session (user hasn't confirmed yet)
     * 6. Redirect to verification page
     *
     * @param Request $request HTTP request (contains _action, food_name, amount, unit, receipt file)
     * @param SessionInterface $session User session (stores receipt preview)
     * @param EntityManagerInterface $entityManager Doctrine ORM
     * @param UsdaFoodDataCentralClient $fdc USDA API client
     * @param ReceiptOcrExtractorService $receiptOcr OpenAI vision service for receipt scanning
     * @param ReceiptImageBlurChecker $receiptBlurChecker Image quality check
     * @return Response Redirect to home or verification page
     */
    #[Route('/food/input', name: 'app_food_input', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        UsdaFoodDataCentralClient $fdc,
        ReceiptOcrExtractorService $receiptOcr,
        ReceiptImageBlurChecker $receiptBlurChecker,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('_action', '');

            if ($action === 'manual') {
                return $this->handleManual($request, $user, $entityManager, $fdc, $session);
            }

            if ($action === 'receipt') {
                return $this->handleReceipt($request, $fdc, $receiptOcr, $session, $receiptBlurChecker);
            }

            $this->addFlash('error', 'Unknown form action.');

            return $this->redirectToRoute('app_home');
        }

        return $this->redirect($this->generateUrl('app_home'), Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Displays and processes receipt verification form.
     *
     * After user uploads receipt and OCR extracts items, they see this page
     * where they can review extracted lines, edit amounts/units, and select
     * which lines to save
     * GET request: Display verification form with receipt preview
     * POST request: Validate user selections and save selected intakes
     *
     * Flow:
     * 1. Retrieve receipt preview from session (set by handleReceipt)
     * 2. If no preview: redirect to home with warning
     * 3. For POST: validate each selected row
     *    - Check amount is positive number
     *    - Check USDA match exists
     *    - Validate unit is valid FoodIntakeUnit
     * 4. Save all validated rows to database
     * 5. Clear receipt preview from session
     * 6. Redirect to home with success message
     *
     * @param Request $request HTTP request (contains rows data with include/amount/unit)
     * @param SessionInterface $session User session (contains receipt preview)
     * @param EntityManagerInterface $entityManager Doctrine ORM
     * @return Response Rendered form or redirect
     */
    #[Route('/food/input/receipt/verify', name: 'app_food_receipt_verify', methods: ['GET', 'POST'])]
    public function verifyReceipt(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $previewRows = $session->get(self::RECEIPT_PREVIEW_SESSION_KEY);
        if (!\is_array($previewRows) || $previewRows === []) {
            $this->addFlash('warning', 'No receipt preview. Upload and scan a receipt first.');

            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            return $this->handleReceiptVerifySubmit($request, $session, $entityManager, $user, $previewRows);
        }

        return $this->renderReceiptVerifyForm($session, $previewRows);
    }
    /**
     * Cancels receipt preview and clears session data.
     * Redirects back to home with original parameters.
     *
     * @param SessionInterface $session User session
     * @return Response Redirect to home
     */
    #[Route('/food/input/receipt/cancel', name: 'app_food_receipt_cancel', methods: ['GET'])]
    public function cancelReceiptPreview(SessionInterface $session): Response
    {
        if (!$session->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $targetDate = $this->selectedDateFromSession($session);
        $returnView = $this->returnViewFromSession($session);
        $returnShowWeekends = $this->returnShowWeekendsFromSession($session);
        $session->remove(self::RECEIPT_PREVIEW_SESSION_KEY);
        $session->remove(self::RECEIPT_TARGET_DATE_SESSION_KEY);
        $session->remove(self::RECEIPT_RETURN_VIEW_SESSION_KEY);
        $session->remove(self::RECEIPT_SHOW_WEEKENDS_SESSION_KEY);

        return $this->redirectToRoute('app_home', [
            'view' => $returnView,
            'date' => $targetDate->format('Y-m-d'),
            'show_weekends' => $returnShowWeekends,
        ]);
    }
    /**
     * Displays and processes food intake edit form.
     * User can edit amount, unit, and date & time of a logged food intake.
     *
     * GET request: Display edit form with current values
     * POST request: Validate new values and save to database
     * Flow:
     * 1. Find food intake by ID
     * 2. Check current user owns it
     * 3. For GET: render form with current values
     * 4. For POST: validate new values
     *    - Parse date/time
     *    - Check amount is positive number
     *    - Validate unit
     * 5. Update FoodIntake entity
     * 6. Save to database
     * 7. Redirect to home with success message
     *
     * @param int $id Food intake ID
     * @param Request $request HTTP request (contains consumed_at, amount, unit)
     * @param SessionInterface $session User session
     * @param EntityManagerInterface $entityManager Doctrine ORM
     * @return Response Rendered form or redirect
     */
    #[Route('/food/intake/{id}/edit', name: 'app_food_intake_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editIntake(
        int $id,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $intake = $entityManager->getRepository(FoodIntake::class)->find($id);
        if (!$intake instanceof FoodIntake) {
            throw $this->createNotFoundException('Food intake not found.');
        }

        if ($intake->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot edit this entry.');
        }

        $returnView = (string) ($request->query->get('view') ?? $request->request->get('_return_view', 'week'));
        if (!\in_array($returnView, ['day', 'week'], true)) {
            $returnView = 'week';
        }

        $returnShowWeekends = (string) ($request->query->get('show_weekends') ?? $request->request->get('_return_show_weekends', '1'));
        $returnShowWeekends = $returnShowWeekends === '0' ? '0' : '1';

        if ($request->isMethod('POST')) {
            $rawWhen = trim((string) $request->request->get('consumed_at', ''));
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $rawWhen);
            if ($parsed === false) {
                $this->addFlash('error', 'Please enter a valid date and time.');

                return $this->renderEditIntakeForm($intake, $returnView, $returnShowWeekends);
            }

            $amountRaw = $request->request->get('amount');
            $amount = is_numeric($amountRaw) ? (float) $amountRaw : null;
            if ($amount === null || $amount <= 0) {
                $this->addFlash('error', 'Amount must be a positive number.');

                return $this->renderEditIntakeForm($intake, $returnView, $returnShowWeekends);
            }

            $unitRaw = (string) $request->request->get('unit', FoodIntakeUnit::Gram->value);
            $unit = FoodIntakeUnit::tryFrom($unitRaw) ?? FoodIntakeUnit::Gram;

            $intake->setCreatedAt($parsed);
            $intake->setAmount($amount);
            $intake->setUnit($unit);
            $entityManager->flush();

            $this->addFlash('success', 'Food entry updated.');

            return $this->redirectToRoute('app_home', [
                'view' => $returnView,
                'date' => $parsed->format('Y-m-d'),
                'show_weekends' => $returnShowWeekends,
            ]);
        }

        return $this->renderEditIntakeForm($intake, $returnView, $returnShowWeekends);
    }

    private function renderEditIntakeForm(FoodIntake $intake, string $returnView, string $returnShowWeekends): Response
    {
        return $this->render('food_intake/edit.html.twig', [
            'intake' => $intake,
            'returnView' => $returnView,
            'returnShowWeekends' => $returnShowWeekends,
        ]);
    }
    /**
     * Deletes a food intake entry.
     * Flow:
     * 1. Find food intake by ID
     * 2. Check current user owns it
     * 3. Delete from database
     * 4. Redirect to home with success message
     *
     * @param int $id Food intake ID
     * @param Request $request HTTP request (contains _return_view, _return_show_weekends)
     * @param SessionInterface $session User session
     * @param EntityManagerInterface $entityManager Doctrine ORM
     * @return Response Redirect to home
     */
    #[Route('/food/intake/{id}/delete', name: 'app_food_intake_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteIntake(
        int $id,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $intake = $entityManager->getRepository(FoodIntake::class)->find($id);
        if (!$intake instanceof FoodIntake) {
            throw $this->createNotFoundException('Food intake not found.');
        }

        if ($intake->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot delete this entry.');
        }

        $returnView = (string) $request->request->get('_return_view', 'week');
        if (!\in_array($returnView, ['day', 'week'], true)) {
            $returnView = 'week';
        }

        $returnShowWeekends = (string) $request->request->get('_return_show_weekends', '1');
        $returnShowWeekends = $returnShowWeekends === '0' ? '0' : '1';

        $returnDate = $intake->getCreatedAt()?->format('Y-m-d') ?? (new \DateTimeImmutable('today'))->format('Y-m-d');

        $entityManager->remove($intake);
        $entityManager->flush();

        $this->addFlash('success', 'Food entry deleted.');

        return $this->redirectToRoute('app_home', [
            'view' => $returnView,
            'date' => $returnDate,
            'show_weekends' => $returnShowWeekends,
        ]);
    }

    private function handleManual(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UsdaFoodDataCentralClient $fdc,
        SessionInterface $session,
    ): Response {
        $targetDate = $this->selectedDateFromRequest($request);
        $foodName = trim((string) $request->request->get('food_name', ''));
        $amountRaw = $request->request->get('amount');
        $unitRaw = (string) $request->request->get('unit', FoodIntakeUnit::Gram->value);

        if ($foodName === '') {
            $this->addFlash('error', 'Please enter a food name to search.');
            return $this->redirectToSelectedHome($request, $targetDate);
        }

        $amount = is_numeric($amountRaw) ? (float) $amountRaw : null;
        if ($amount === null || $amount <= 0) {
            $this->addFlash('error', 'Amount must be a positive number.');
            return $this->redirectToSelectedHome($request, $targetDate);
        }

        $unit = FoodIntakeUnit::tryFrom($unitRaw) ?? FoodIntakeUnit::Gram;

        try {
            $foods = $fdc->searchFoods($foodName);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToSelectedHome($request, $targetDate);
        }

        if ($foods === []) {
            $this->addFlash('warning', 'No FoodData Central match for "'.$foodName.'". Nothing was saved.');
            return $this->redirectToSelectedHome($request, $targetDate);
        }

        // Store in session
        $session->set('_pending_foods', [
            'foods' => $foods,
            'foodName' => $foodName,
            'amount' => $amount,
            'unit' => $unit,
            'targetDate' => $targetDate,
            'returnView' => $this->returnViewFromRequest($request),
            'returnShowWeekends' => $this->returnShowWeekendsFromRequest($request),
        ]);

        return $this->redirectToRoute('app_home', [
            'view' => $this->returnViewFromRequest($request),
            'date' => $targetDate->format('Y-m-d'),
            'show_weekends' => $this->returnShowWeekendsFromRequest($request),
        ]);
    }

    private function handleReceipt(
        Request $request,
        UsdaFoodDataCentralClient $fdc,
        ReceiptOcrExtractorService $receiptOcr,
        SessionInterface $session,
        ReceiptImageBlurChecker $receiptBlurChecker,
    ): Response {
        $targetDate = $this->selectedDateFromRequest($request);
        $file = $request->files->get('receipt');
        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Please choose a valid receipt image (JPEG, PNG, or WebP).');

            return $this->redirectToSelectedHome($request, $targetDate);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = UploadedMimeType::guess($file);
        if ($mime === null || !\in_array($mime, $allowed, true)) {
            $this->addFlash('error', 'Receipt must be JPEG, PNG, or WebP.');

            return $this->redirectToSelectedHome($request, $targetDate);
        }

        if ($receiptBlurChecker->isTooBlurry($file)) {
            $this->addFlash(
                'error',
                'This receipt photo looks too blurry or out of focus. Please retake it with better lighting and steadier hands.',
            );

            return $this->redirectToSelectedHome($request, $targetDate);
        }

        ini_set('max_execution_time', (string) self::RECEIPT_MAX_EXECUTION_SECONDS);
        if (\function_exists('set_time_limit')) {
            @set_time_limit(self::RECEIPT_MAX_EXECUTION_SECONDS);
        }

        try {
            $items = $receiptOcr->extractItemsFromUploadedImage($file);
        } catch (ReceiptNotReadableException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToSelectedHome($request, $targetDate);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Receipt parsing failed: '.$e->getMessage());

            return $this->redirectToSelectedHome($request, $targetDate);
        }

        if ($items === []) {
            $this->addFlash('warning', 'No product lines were extracted from the receipt.');

            return $this->redirectToSelectedHome($request, $targetDate);
        }

        $previewRows = [];

        foreach ($items as $item) {
            $name = $item['name'];
            $searchQuery = $item['usdaSearch'] ?? $name;
            [$amount, $unit] = $this->amountAndUnitFromReceiptItem($item);

            try {
                $match = $fdc->searchFirstFood($searchQuery);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToSelectedHome($request, $targetDate);
            }

            $previewRows[] = [
                'ocrName' => $name,
                'usdaSearch' => $searchQuery,
                'fdcId' => $match !== null ? $match['fdcId'] : null,
                'fdcName' => $match !== null ? $this->truncateSrcName($match['description']) : null,
                'amount' => $amount,
                'unit' => $unit->value,
            ];
        }

        $session->set(self::RECEIPT_PREVIEW_SESSION_KEY, $previewRows);
        $session->set(self::RECEIPT_TARGET_DATE_SESSION_KEY, $targetDate->format('Y-m-d'));
        $session->set(self::RECEIPT_RETURN_VIEW_SESSION_KEY, $this->returnViewFromRequest($request));
        $session->set(self::RECEIPT_SHOW_WEEKENDS_SESSION_KEY, $this->returnShowWeekendsFromRequest($request));

        $unmatched = \count(array_filter($previewRows, static fn (array $r): bool => $r['fdcId'] === null));
        if ($unmatched > 0) {
            $this->addFlash(
                'warning',
                sprintf(
                    '%d line(s) had no USDA FoodData Central match. You can review and save only matched lines.',
                    $unmatched,
                ),
            );
        }

        return $this->redirectToRoute('app_food_receipt_verify');
    }

    /**
     * @param list<array{ocrName: string, usdaSearch: string, fdcId: int|null, fdcName: string|null, amount: float, unit: string}> $previewRows
     */
    private function handleReceiptVerifySubmit(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        User $user,
        array $previewRows,
    ): Response {
        $targetDate = $this->selectedDateFromSession($session);
        $all = $request->request->all();
        $rowsInput = isset($all['rows']) && \is_array($all['rows']) ? $all['rows'] : [];

        $errors = [];
        $toSave = [];

        foreach ($previewRows as $i => $row) {
            $input = $rowsInput[$i] ?? [];
            if (!\is_array($input)) {
                $input = [];
            }

            $include = isset($input['include']) && (string) $input['include'] === '1';
            if (!$include) {
                continue;
            }

            if ($row['fdcId'] === null) {
                $errors[] = 'Cannot save row #'.($i + 1).': no USDA match for that line.';

                continue;
            }

            $amountRaw = $input['amount'] ?? null;
            $amount = is_numeric($amountRaw) ? (float) $amountRaw : null;
            if ($amount === null || $amount <= 0) {
                $errors[] = 'Row #'.($i + 1).': enter a positive amount.';

                continue;
            }

            $unitRaw = (string) ($input['unit'] ?? FoodIntakeUnit::Gram->value);
            $unit = FoodIntakeUnit::tryFrom($unitRaw) ?? FoodIntakeUnit::Gram;

            $toSave[] = [
                'fdcId' => (int) $row['fdcId'],
                'fdcName' => $row['fdcName'],
                'amount' => $amount,
                'unit' => $unit,
            ];
        }

        if ($errors !== []) {
            $this->addFlash('error', implode(' ', $errors));

            return $this->renderReceiptVerifyForm($session, $previewRows);
        }

        if ($toSave === []) {
            $this->addFlash('warning', 'No lines selected. Check the rows you want to save, then confirm.');

            return $this->renderReceiptVerifyForm($session, $previewRows);
        }

        $createdAt = $this->createdAtForSelectedDate($targetDate);
        foreach ($toSave as $data) {
            $intake = new FoodIntake();
            $intake->setUser($user);
            $intake->setSrcId($data['fdcId']);
            $intake->setSrcName($data['fdcName']);
            $intake->setAmount($data['amount']);
            $intake->setUnit($data['unit']);
            $intake->setCreatedAt($createdAt);
            $entityManager->persist($intake);
        }

        $entityManager->flush();
        $returnView = $this->returnViewFromSession($session);
        $returnShowWeekends = $this->returnShowWeekendsFromSession($session);
        $session->remove(self::RECEIPT_PREVIEW_SESSION_KEY);
        $session->remove(self::RECEIPT_TARGET_DATE_SESSION_KEY);
        $session->remove(self::RECEIPT_RETURN_VIEW_SESSION_KEY);
        $session->remove(self::RECEIPT_SHOW_WEEKENDS_SESSION_KEY);
        $this->addFlash('success', sprintf('Saved %d intake line(s) from the receipt.', \count($toSave)));

        return $this->redirectToRoute('app_home', [
            'view' => $returnView,
            'date' => $targetDate->format('Y-m-d'),
            'show_weekends' => $returnShowWeekends,
        ]);
    }

    /**
     * @param list<array{ocrName: string, usdaSearch: string, fdcId: int|null, fdcName: string|null, amount: float, unit: string}> $previewRows
     */
    private function renderReceiptVerifyForm(SessionInterface $session, array $previewRows): Response
    {
        return $this->render('food_intake/receipt_verify.html.twig', [
            'rows' => $previewRows,
            'userName' => $session->get('user_name'),
            'targetDate' => $this->selectedDateFromSession($session)->format('Y-m-d'),
        ]);
    }

    private function selectedDateFromRequest(Request $request): \DateTimeImmutable
    {
        return $this->parseDateString((string) $request->request->get('consumed_date', ''))
            ?? (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
    }

    private function selectedDateFromSession(SessionInterface $session): \DateTimeImmutable
    {
        return $this->parseDateString((string) $session->get(self::RECEIPT_TARGET_DATE_SESSION_KEY, ''))
            ?? (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
    }

    private function parseDateString(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }

    private function createdAtForSelectedDate(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();

        return $date->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
    }

    private function redirectToSelectedHome(Request $request, \DateTimeImmutable $targetDate): Response
    {
        return $this->redirectToRoute('app_home', [
            'view' => $this->returnViewFromRequest($request),
            'date' => $targetDate->format('Y-m-d'),
            'show_weekends' => $this->returnShowWeekendsFromRequest($request),
        ]);
    }

    private function returnViewFromRequest(Request $request): string
    {
        $view = (string) $request->request->get('_return_view', 'week');

        return \in_array($view, ['day', 'week'], true) ? $view : 'week';
    }

    private function returnViewFromSession(SessionInterface $session): string
    {
        $view = (string) $session->get(self::RECEIPT_RETURN_VIEW_SESSION_KEY, 'week');

        return \in_array($view, ['day', 'week'], true) ? $view : 'week';
    }

    private function returnShowWeekendsFromRequest(Request $request): string
    {
        return (string) $request->request->get('_return_show_weekends', '1') === '0' ? '0' : '1';
    }

    private function returnShowWeekendsFromSession(SessionInterface $session): string
    {
        return (string) $session->get(self::RECEIPT_SHOW_WEEKENDS_SESSION_KEY, '1') === '0' ? '0' : '1';
    }

    /**
     * @param array{name: string, weight?: array{value: float, unit: string}} $item
     *
     * @return array{0: float, 1: FoodIntakeUnit}
     */
    private function amountAndUnitFromReceiptItem(array $item): array
    {
        if (!isset($item['weight'])) {
            return [1.0, FoodIntakeUnit::Unit];
        }

        $w = $item['weight'];
        $val = $w['value'];
        $u = strtolower($w['unit']);

        $grams = match ($u) {
            'g' => $val,
            'kg' => $val * 1000,
            'mg' => $val / 1000,
            'ml' => $val,
            'l' => $val * 1000,
            'oz' => $val * 28.3495,
            'lb' => $val * 453.592,
            default => null,
        };

        if ($grams === null) {
            return [1.0, FoodIntakeUnit::Unit];
        }

        return [$grams, FoodIntakeUnit::Gram];
    }

    private function truncateSrcName(string $description): string
    {
        $s = trim($description);
        if (\strlen($s) <= 512) {
            return $s;
        }

        return substr($s, 0, 509).'...';
    }
    #[Route('/food/input/confirm', name: 'app_food_input_confirm', methods: ['POST'])]
    public function confirmFoodSelection(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        UsdaFoodDataCentralClient $fdc,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');
            return $this->redirectToRoute('app_login');
        }

        $pendingFoods = $session->get('_pending_foods');
        if (!is_array($pendingFoods)) {
            $this->addFlash('error', 'No pending food selection.');
            return $this->redirectToRoute('app_home');
        }

        $targetDate = $pendingFoods['targetDate'];
        $fdcId = (int) $request->request->get('selected_fdc_id', 0);
        $amount = $pendingFoods['amount'];
        $unit = $pendingFoods['unit'];

        if ($fdcId <= 0) {
            $this->addFlash('error', 'Please select a food.');
            return $this->redirectToRoute('app_home');
        }

        try {
            $description = $fdc->getFoodDescription($fdcId);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Could not get food details: ' . $e->getMessage());
            return $this->redirectToRoute('app_home');
        }

        $foodName = $description ?? 'Food #'.$fdcId;

        $intake = new FoodIntake();
        $intake->setUser($user);
        $intake->setSrcId($fdcId);
        $intake->setSrcName($this->truncateSrcName($foodName));
        $intake->setAmount($amount);
        $intake->setUnit($unit);
        $intake->setCreatedAt($this->createdAtForSelectedDate($targetDate));

        $entityManager->persist($intake);
        $entityManager->flush();

        // Clear session
        $session->remove('_pending_foods');

        $this->addFlash('success', 'Saved intake: '.$foodName);

        return $this->redirectToRoute('app_home', [
            'view' => $pendingFoods['returnView'],
            'date' => $targetDate->format('Y-m-d'),
            'show_weekends' => $pendingFoods['returnShowWeekends'],
        ]);
    }

}
