<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PPFinances\Wealthsimple\WealthsimpleAPI;

final class TestableWealthsimpleAPI extends WealthsimpleAPI
{
    public array $calls = [];
    public array $responses = [];

    public function __construct()
    {
    }

    public static function query(string $name): string
    {
        return parent::getGraphQLQuery($name);
    }

    protected function getTokenInfo()
    {
        return (object) ['identity_canonical_id' => 'identity-1'];
    }

    protected function doGraphQLQuery(string $query_name, array $variables, string $data_response_path, string $expect_type, ?callable $filter = NULL, bool $load_all_pages = FALSE)
    {
        $this->calls[] = compact('query_name', 'variables', 'data_response_path', 'expect_type', 'load_all_pages');
        return $this->responses[$query_name];
    }
}

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, TRUE) . "\nActual: " . var_export($actual, TRUE));
    }
}

function assertTrueValue(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

$accountsQuery = TestableWealthsimpleAPI::query('FetchIdentityNetWorthAccounts');
$financialsQuery = TestableWealthsimpleAPI::query('FetchIdentityNetWorthFinancials');
assertTrueValue(strpos($accountsQuery, 'branch') !== FALSE, 'The net-worth accounts query must include custodian branch.');
assertTrueValue(strpos($financialsQuery, 'historicalDaily') !== FALSE, 'The net-worth financials query must request history.');

$api = new TestableWealthsimpleAPI();
$api->responses['FetchIdentityNetWorthAccounts'] = (object) [
    'accounts' => (object) [
        'edges' => [
            (object) ['node' => (object) [
                'id' => 'account-1',
                'nickname' => NULL,
                'unifiedAccountType' => 'SELF_DIRECTED_TFSA',
                'accountOwnerConfiguration' => 'SINGLE_OWNER',
                'accountFeatures' => [],
                'custodianAccounts' => [(object) ['id' => 'H123', 'status' => 'open', 'branch' => 'WS']],
            ]],
        ],
    ],
    'externalFinancialEntities' => [(object) ['id' => 'external-1']],
];
$accounts = $api->getNetWorthAccounts();
assertSameValue('TFSA: self-directed', $accounts->accounts[0]->description, 'Internal accounts must receive a description.');
assertSameValue('H123', $accounts->accounts[0]->number, 'Internal accounts must receive their visible account number.');
assertSameValue('external-1', $accounts->externalFinancialEntities[0]->id, 'External entities must be returned.');
assertSameValue([
    'identityId' => 'identity-1',
    'pageSize' => 100,
    'filter' => ['archived' => FALSE, 'closed' => FALSE],
], $api->calls[0]['variables'], 'Net-worth accounts variables must match the upstream API.');

$api = new TestableWealthsimpleAPI();
$api->responses['FetchIdentityNetWorthFinancials'] = (object) [
    'current' => (object) ['balance' => (object) ['amount' => '123.45', 'currency' => 'USD']],
    'historicalDaily' => (object) [
        'edges' => [(object) ['node' => (object) ['date' => '2026-09-01']]],
    ],
];
$netWorth = $api->getNetWorthWithHistory('OWN', 'USD', '2026-09-01', '2026-09-21', ['account-1'], ['external-1']);
assertSameValue('123.45', $netWorth->balance->amount, 'Current balance must be returned.');
assertSameValue('2026-09-01', $netWorth->historicalDaily[0]->date, 'Historical edges must be unwrapped.');
assertSameValue([
    'identityId' => 'identity-1',
    'accountIds' => ['account-1'],
    'externalFinancialEntityIds' => ['external-1'],
    'accountScope' => 'OWN',
    'currency' => 'USD',
    'startDate' => '2026-09-01',
    'endDate' => '2026-09-21',
], $api->calls[0]['variables'], 'Explicit net-worth filters and dates must be preserved.');

$api = new TestableWealthsimpleAPI();
$api->responses['FetchIdentityNetWorthAccounts'] = (object) [
    'accounts' => (object) ['edges' => [(object) ['node' => (object) [
        'id' => 'account-2',
        'nickname' => 'Savings',
        'unifiedAccountType' => 'CASH',
        'accountOwnerConfiguration' => 'SINGLE_OWNER',
        'accountFeatures' => [],
        'custodianAccounts' => [],
    ]]]],
    'externalFinancialEntities' => [(object) ['id' => 'external-2']],
];
$api->responses['FetchIdentityNetWorthFinancials'] = (object) [
    'current' => (object) ['balance' => (object) ['amount' => '42', 'currency' => 'CAD']],
    'historicalDaily' => (object) ['edges' => []],
];
$before = new DateTimeImmutable('today -30 days');
$api->getNetWorthWithHistory();
$after = new DateTimeImmutable('today -30 days');
assertSameValue('FetchIdentityNetWorthAccounts', $api->calls[0]['query_name'], 'Omitted filters must trigger account discovery.');
assertSameValue(['account-2'], $api->calls[1]['variables']['accountIds'], 'Discovered internal account IDs must be used.');
assertSameValue(['external-2'], $api->calls[1]['variables']['externalFinancialEntityIds'], 'Discovered external entity IDs must be used.');
assertSameValue(date('Y-m-d'), $api->calls[1]['variables']['endDate'], 'The default end date must be today.');
$startDate = new DateTimeImmutable($api->calls[1]['variables']['startDate']);
assertTrueValue($startDate >= $before && $startDate <= $after, 'The default start date must be 30 days before today.');

echo "NetWorthTest passed\n";
