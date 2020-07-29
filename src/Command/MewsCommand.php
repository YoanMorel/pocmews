<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;

class MewsCommand extends Command
{
    private $appKernel;
    private $credentials = [
        'ClientToken' => 'E0D439EE522F44368DC78E1BFB03710C-D24FB11DBE31D4621C4817E028D9E1D',
        'AccessToken' => '7059D2C25BF64EA681ACAB3A00B859CC-D91BFF2B1E3047A3E0DEC1D57BE1382',
        'Client'      => 'Sample Client 1.0.0',
    ];

    public function __construct(KernelInterface $appKernel)
    {
        $this->appKernel = $appKernel;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('import:mews')
            ->setDescription('Import mews')
            ->addArgument('type', InputArgument::REQUIRED, 'Entrer le type de données [CB, Outlets]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $io = new SymfonyStyle($input, $output);

        $dataType = [
            'CB',
            'Outlets'
        ];

        $argument = $input->getArgument('type');
        $textOutput = 'Argument ' . $argument . ' Séléctionné. Téléchargement des données';

        if (in_array($argument, $dataType) && $argument == 'CB') {
            $io->text($textOutput);
            $this->creditCard();
            $io->success('Données téléchargées avec succès');
        } elseif (in_array($argument, $dataType) && $argument == 'Outlets') {
            $io->text($textOutput);
            $this->outlets();
            $io->success('Données téléchargées avec succès');
        } else {
            $io->error('Aucun des arguments de correspond à la liste donnée entre crochet');
        }

        return 1;
    }

    /**
     * creditCard
     * 
     * Example de téléchargement de données carte bancaire
     *
     * @return void
     */
    protected function creditCard()
    {
        ini_set('memory_limit', '-1');

        $client = new \GuzzleHttp\Client();
        $responseAccountingItems = $client->post(
            'https://demo.mews.li/api/connector/v1/accountingItems/getAll',
            [
                'json' => array_merge($this->credentials, [
                    'StartUtc' => '2020-01-05T00:00:00Z',
                    'EndUtc'   => '2020-03-10T00:00:00Z'
                ])
            ]
        );

        $accountingItems = json_decode($responseAccountingItems->getBody()->getContents(), true);

        $creditCardPayments = [];

        foreach ($accountingItems['AccountingItems'] as $accountingItem) {
            if (array_key_exists('CreditCardId', $accountingItem) && $accountingItem['CreditCardId'] != null) {
                $responseCreditCard = $client->post(
                    'https://demo.mews.li/api/connector/v1/creditCards/getAll',
                    [
                        'json' => array_merge($this->credentials, [
                            'CreditCardIds' => [$accountingItem['CreditCardId']]
                        ])
                    ]
                );

                $creditCard = json_decode($responseCreditCard->getBody()->getContents(), true);

                if (array_key_exists('CustomerId', $accountingItem) && $accountingItem['CustomerId'] != null) {
                    $responseCustomer = $client->post(
                        'https://demo.mews.li/api/connector/v1/customers/getAll',
                        [
                            'json' => array_merge($this->credentials, [
                                'CustomerIds' => [$accountingItem['CustomerId']]
                            ])
                        ]
                    );

                    $customer = json_decode($responseCustomer->getBody()->getContents(), true);

                    $billingDate = null;

                    if ($accountingItem['BillId'] != null) {
                        $responseBill = $client->post(
                            'https://demo.mews.li/api/connector/v1/bills/getAll',
                            [
                                'json' => array_merge($this->credentials, [
                                    'BillIds' => [$accountingItem['BillId']]
                                ])
                            ]
                        );

                        $bill = json_decode($responseBill->getBody()->getContents(), true);
                        $billingDate = date("d-m-Y H:i:s", strtotime($bill['Bills'][0]['CreatedUtc']));
                    }

                    $creditCardPayments[] = [
                        'CustomerName' => implode(' ', [$customer['Customers'][0]['FirstName'], $customer['Customers'][0]['LastName']]),
                        'CardNumber'   => $creditCard['CreditCards'][0]['ObfuscatedNumber'],
                        'CardType'     => $creditCard['CreditCards'][0]['Type'],
                        'DateTime'     => $billingDate
                    ];
                }
            }
        }

        dump($creditCardPayments);
    }

    protected function outlets()
    {
        ini_set('memory_limit', '-1');

        $client = new \GuzzleHttp\Client();

        $responseOutletItems = $client->post(
            'https://demo.mews.li/api/connector/v1/outletItems/getAll',
            [
                'json' => array_merge($this->credentials, [
                    'ConsumedUtc' => [
                        'StartUtc' => '2020-01-05T00:00:00Z',
                        'EndUtc' => '2020-03-05T00:00:00Z'
                    ]
                ])
            ]
        );

        $outletItems = json_decode($responseOutletItems->getBody()->getContents(), true);

        dump($outletItems);
    }
}
