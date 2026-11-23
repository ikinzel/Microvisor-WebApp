<?php

namespace App\Command;

use App\Entity\Disease;
use App\Repository\DiseaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds common conditions with illustrative dietary intake suggestions (not medical advice).
 */
#[AsCommand(name: 'app:seed-diseases', description: 'Upsert reference diseases and intake suggestions by code')]
final class SeedDiseasesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DiseaseRepository $diseaseRepository,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<array{code: string, label: string, description: string, intakeSuggestions: array<string, string>}> $definitions */
        $definitions = [
            [
                'code' => 'IRON_DEFICIENCY_ANEMIA',
                'label' => 'Iron-deficiency anemia',
                'description' => 'Iron stores are low; diet often emphasizes absorbable iron and cofactors.',
                'intakeSuggestions' => [
                    'iron' => 'Prioritize iron-rich foods (lean meat, pulses, fortified cereals); pair plant iron with vitamin C.',
                    'vitamin_c' => 'Include vitamin C sources (citrus, peppers) with meals to boost non-heme iron absorption.',
                    'folate' => 'Maintain folate intake (leafy greens, legumes) alongside iron repletion plans.',
                ],
            ],
            [
                'code' => 'CELIAC_DISEASE',
                'label' => 'Celiac disease',
                'description' => 'Malabsorption while gut heals; several micronutrients often need attention.',
                'intakeSuggestions' => [
                    'iron' => 'Choose iron-rich gluten-free foods and discuss monitoring with your clinician.',
                    'calcium' => 'Ensure adequate calcium (fortified drinks, dairy or alternatives) on a strict GFD.',
                    'vitamin_d' => 'Confirm vitamin D intake (oily fish, eggs, supplements as advised).',
                    'folate' => 'Emphasize folate from vegetables and fortified gluten-free staples.',
                    'zinc' => 'Include zinc sources (meat, nuts, seeds) suited to your tolerated foods.',
                ],
            ],
            [
                'code' => 'CHRONIC_KIDNEY_DISEASE',
                'label' => 'Chronic kidney disease',
                'description' => 'Electrolytes and minerals are highly individualized (dietitian-guided).',
                'intakeSuggestions' => [
                    'potassium' => 'Potassium needs vary widely in CKD—follow personalized limits rather than generic targets.',
                    'calcium' => 'Calcium balance depends on phosphate and meds; coordinate intake with your renal team.',
                    'vitamin_d' => 'Vitamin D management often requires lab-guided supplementation.',
                ],
            ],
            [
                'code' => 'TYPE_2_DIABETES',
                'label' => 'Type 2 diabetes mellitus',
                'description' => 'Whole-food patterns matter alongside glycemic management.',
                'intakeSuggestions' => [
                    'magnesium' => 'Include magnesium-rich foods (nuts, seeds, whole grains, legumes) within your carb plan.',
                    'chromium' => 'Chromium occurs widely in foods; prioritize balanced meals over isolated supplements.',
                    'zinc' => 'Ensure adequate zinc from varied whole foods as part of balanced meal planning.',
                ],
            ],
            [
                'code' => 'OSTEOPOROSIS',
                'label' => 'Osteoporosis',
                'description' => 'Bone-support nutrients usually align with calcium, vitamin D, vitamin K, magnesium.',
                'intakeSuggestions' => [
                    'calcium' => 'Spread calcium across meals from dairy or fortified alternatives.',
                    'vitamin_d' => 'Maintain dietary vitamin D sources and sunlight habits per clinician advice.',
                    'vitamin_k' => 'Eat vitamin K–rich greens regularly unless anticoagulation guidance says otherwise.',
                    'magnesium' => 'Add magnesium sources (whole grains, nuts) to complement bone health habits.',
                ],
            ],
            [
                'code' => 'INFLAMMATORY_BOWEL_DISEASE',
                'label' => 'Inflammatory bowel disease',
                'description' => 'Flares and malabsorption can shift needs for iron, D, folate, zinc, magnesium.',
                'intakeSuggestions' => [
                    'iron' => 'Iron may be needed in absorbable forms during active disease—coordinate with care team.',
                    'vitamin_d' => 'Screen and address vitamin D status; diet plus supplements as prescribed.',
                    'zinc' => 'Include tolerated zinc sources (meat, seeds) when intake is limited.',
                    'folate' => 'Folate-rich foods or supplements may be used under medical guidance.',
                    'magnesium' => 'Replace losses if diarrhea is present; forms and doses are individualized.',
                ],
            ],
            [
                'code' => 'HYPOTHYROIDISM',
                'label' => 'Hypothyroidism',
                'description' => 'Iron and vitamin D shortfalls are common companions to thyroid care.',
                'intakeSuggestions' => [
                    'iron' => 'Ensure adequate iron intake; deficiency can overlap with thyroid symptoms.',
                    'vitamin_d' => 'Support vitamin D adequacy as part of overall metabolic health.',
                    'iodine' => 'Avoid self-supplementing iodine without advice—needs depend on etiology and region.',
                ],
            ],
            [
                'code' => 'HYPERTENSION',
                'label' => 'Hypertension',
                'description' => 'DASH-style patterns emphasize potassium- and magnesium-rich produce.',
                'intakeSuggestions' => [
                    'potassium' => 'Emphasize fruits and vegetables for potassium unless contraindicated (e.g. advanced CKD).',
                    'magnesium' => 'Choose nuts, legumes, and whole grains as tolerated within energy needs.',
                ],
            ],
            [
                'code' => 'PERNICIOUS_ANEMIA',
                'label' => 'Pernicious anemia (B12 deficiency)',
                'description' => 'Intrinsic factor loss impairs B12 absorption; injections often replace pills.',
                'intakeSuggestions' => [
                    'vitamin_b12' => 'B12 replenishment is usually managed with prescribed injections or high oral doses—not diet alone.',
                    'folate' => 'Folate should be coordinated with B12 correction to avoid masking deficiency.',
                ],
            ],
            [
                'code' => 'LIVER_CIRRHOSIS',
                'label' => 'Liver cirrhosis',
                'description' => 'Protein-energy nutrition, zinc, vitamin D, and vitamin A toxicity risk vary with stage.',
                'intakeSuggestions' => [
                    'zinc' => 'Zinc-containing foods may be emphasized when deficiency is suspected—under hepatology guidance.',
                    'vitamin_d' => 'Vitamin D repletion is common; dosing is individualized.',
                    'vitamin_a' => 'Avoid high-dose vitamin A supplements without hepatology clearance (toxicity risk).',
                ],
            ],
        ];

        foreach ($definitions as $row) {
            $existing = $this->diseaseRepository->findOneBy(['code' => $row['code']]);
            if ($existing instanceof Disease) {
                $existing->setLabel($row['label']);
                $existing->setDescription($row['description']);
                $existing->setIntakeSuggestions($row['intakeSuggestions']);
                continue;
            }

            $entity = new Disease();
            $entity->setCode($row['code']);
            $entity->setLabel($row['label']);
            $entity->setDescription($row['description']);
            $entity->setIntakeSuggestions($row['intakeSuggestions']);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        $count = \count($this->diseaseRepository->findAll());

        $io->success(sprintf('Disease reference data upserted: %d rows (by code).', $count));

        return Command::SUCCESS;
    }
}
