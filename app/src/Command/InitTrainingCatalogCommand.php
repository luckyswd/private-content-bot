<?php

namespace App\Command;

use App\Entity\TrainingCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:init-training-catalog',
    description: 'Initializes the TrainingCatalog with predefined hierarchical data',
)]
class InitTrainingCatalogCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = [
            'Для дома без инвентаря/ с гантелями' => [
                'На всё тело',
                'На верх тела',
                'На низ тела',
            ],
            'Для дома с резинками' => [
                'На всё тело',
                'На верх тела',
                'На низ тела',
            ],
            'Для зала' => [
                'На всё тело',
                'Ягодицы + ноги + плечи',
                'Гродь + руки',
                'Спина + ягодицы',
            ],
        ];

        $this->createCategories($data);

        $this->em->flush();

        $output->writeln('TrainingCatalog initialized successfully.');
        return Command::SUCCESS;
    }

    private function createCategories(array $categories, ?TrainingCatalog $parent = null): void
    {
        foreach ($categories as $name => $subcategories) {
            if (is_array($subcategories)) {
                $category = new TrainingCatalog();
                $category->setName($name);
                $category->setSubCatalog($parent);
                $category->setCreatedAt(new \DateTime());

                $this->em->persist($category);

                $this->createCategories($subcategories, $category);
            } else {
                $subCategory = new TrainingCatalog();
                $subCategory->setName($subcategories);
                $subCategory->setSubCatalog($parent);
                $subCategory->setCreatedAt(new \DateTime());

                $this->em->persist($subCategory);
            }
        }
    }
}
