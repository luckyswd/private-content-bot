<?php

namespace App\Command;

use App\Entity\Method;
use App\Entity\Price;
use App\Entity\Rate;
use App\Entity\Setting;
use App\Entity\TrainingCatalog;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Repository\RateRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init',
    description: 'init application command',
)]
class InitCommand extends Command
{
    public function __construct(
        private RateRepository $rateRepository,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private SubscriptionRepository $subscriptionRepository,
        string $name = null,
    )
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $data = [
            SubscriptionType::getRUname(SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT) => [
                TrainingCatalog::MAPPING[TrainingCatalog::FULL_BODY],
                TrainingCatalog::MAPPING[TrainingCatalog::UPPER_BODY],
                TrainingCatalog::MAPPING[TrainingCatalog::LOWER_BODY],
            ],
            SubscriptionType::getRUname(SubscriptionType::TRAINING_HOME_WITH_ELASTIC) => [
                TrainingCatalog::MAPPING[TrainingCatalog::FULL_BODY],
                TrainingCatalog::MAPPING[TrainingCatalog::UPPER_BODY],
                TrainingCatalog::MAPPING[TrainingCatalog::LOWER_BODY],
            ],
            SubscriptionType::getRUname(SubscriptionType::TRAINING_FOR_GYM) => [
                TrainingCatalog::MAPPING[TrainingCatalog::FULL_BODY],
                TrainingCatalog::MAPPING[TrainingCatalog::UPPER_BODY],
                TrainingCatalog::MAPPING[TrainingCatalog::LOWER_BODY],
            ],
        ];

        $this->initRates();
        $this->createCategories($data);

        $this->em->flush();
        $io->info('success');
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

                if ($name === SubscriptionType::getRUname(SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT)) {
                    $category->setSubscriptionType(SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT);
                    $category->setMaxAlgorithmCount(8);
                } elseif ($name === 'Для дома с резинками') {
                    $category->setSubscriptionType(SubscriptionType::TRAINING_HOME_WITH_ELASTIC);
                    $category->setMaxAlgorithmCount(7);
                } else {
                    $category->setSubscriptionType(SubscriptionType::TRAINING_FOR_GYM);
                    $category->setMaxAlgorithmCount(11);
                }

                $this->em->persist($category);

                $this->createCategories($subcategories, $category);
            } else {
                $subCategory = new TrainingCatalog();
                $subCategory->setName($subcategories);
                $subCategory->setSubCatalog($parent);
                $subCategory->setCreatedAt(new \DateTime());

                $maxAlgorithmCount = match ($parent->getSubscriptionType()) {
                    SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT => 8,
                    SubscriptionType::TRAINING_HOME_WITH_ELASTIC => 7,
                    SubscriptionType::TRAINING_FOR_GYM => 11,
                    default => null,
                };

                $subCategory->setMaxAlgorithmCount($maxAlgorithmCount);

                $this->em->persist($subCategory);
            }
        }
    }

    private function initRates():void {
        $ratesArray = [
            [
                'name' => '1 неделя',
                'duration' => 'P1W',
                'price' => '990',
                'type' => SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT,
            ],
            [
                'name' => '1 месяц',
                'duration' => 'P1M',
                'price' => '3490',
                'type' => SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT,
            ],
            [
                'name' => '3 месяца',
                'duration' => 'P3M',
                'price' => '4990',
                'type' => SubscriptionType::TRAINING_HOME_WITHOUT_EQUIPMENT,
            ],

            [
                'name' => '1 неделя',
                'duration' => 'P1W',
                'price' => '990',
                'type' => SubscriptionType::TRAINING_HOME_WITH_ELASTIC,
            ],
            [
                'name' => '1 месяц',
                'duration' => 'P1M',
                'price' => '3490',
                'type' => SubscriptionType::TRAINING_HOME_WITH_ELASTIC,
            ],
            [
                'name' => '3 месяца',
                'duration' => 'P3M',
                'price' => '4990',
                'type' => SubscriptionType::TRAINING_HOME_WITH_ELASTIC,
            ],

            [
                'name' => '1 неделя',
                'duration' => 'P1W',
                'price' => '990',
                'type' => SubscriptionType::TRAINING_FOR_GYM,
            ],
            [
                'name' => '1 месяц',
                'duration' => 'P1M',
                'price' => '3490',
                'type' => SubscriptionType::TRAINING_FOR_GYM,
            ],
            [
                'name' => '3 месяца',
                'duration' => 'P3M',
                'price' => '4990',
                'type' => SubscriptionType::TRAINING_FOR_GYM,
            ],
        ];

        foreach ($ratesArray as $item) {
            $rate = new Rate();
            $rate->setName($item['name']);
            $rate->setSubscriptionType($item['type']);

            $duration = new DateInterval($item['duration']);
            $rate->setDuration($duration);
            $this->addPrice($rate, $item['price']);
            $this->em->persist($rate);
        }
    }

    private function initUser():void {
        $users = $this->userRepository->findAll();

        if (count($users) >= 1) {
            return;
        }


        $user = new User();

        $user->setTelegramId(123456);

        $this->em->persist($user);
        $this->em->flush();
    }

    private function initSubscription():void
    {
        $subscriptions = $this->subscriptionRepository->findAll();

        if (count($subscriptions) >= 1) {
            return;
        }

        $user = $this->userRepository->findAll()[0];
        $rate = $this->rateRepository->findAll()[0];


        $user->addSubscription($rate);

        $this->em->flush();
    }

    private function initSettings():void {
        try {
            $firstMessage = new Setting('startMessage', 'Добрый день! Для покупки видео-курса зарядок на каждый день следуйте инструкциям бота👇');
            $this->em->persist($firstMessage);
            $this->em->flush();
        } catch (Exception) {}

        try {
            $methodMessage = new Setting('methodMessage', 'Выберите удобный метод оплаты: ');
            $this->em->persist($methodMessage);
            $this->em->flush();
        } catch (Exception) {}

        try {
            $methodMessage = new Setting('endMessage', 'Больше нету новых видео');
            $this->em->persist($methodMessage);
            $this->em->flush();
        } catch (Exception) {}
    }

    private function addPrice(
        Rate $rate,
        string $price,
    ): void {
        $priceRub = new Price();
        $priceRub->setPrice($price);
        $priceRub->setCurrency(Price::RUB_CURRENCY);

        $rate->addPrice($priceRub);
    }

    private function addMethods():void {
        $method1 = new Method();
        $method1->setName('YКасса');
        $method1->setToken('123');
        $this->em->persist($method1);
        $this->em->flush();
    }
}
