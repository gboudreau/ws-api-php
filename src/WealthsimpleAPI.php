<?php

namespace PPFinances\Wealthsimple;

use PPFinances\Wealthsimple\Exceptions\WSApiException;

function string_contains(?string $haystack, string $needle): bool {
    return stripos($haystack ?? '', $needle) !== FALSE;
}

class WealthsimpleAPI extends WealthsimpleAPIBase
{
    protected static function getGraphQLQuery(string $query_name): string {
        switch ($query_name) {
        case 'FetchAllAccountFinancials': return "query FetchAllAccountFinancials(\$identityId: ID!, \$startDate: Date, \$pageSize: Int = 25, \$cursor: String) {\n  identity(id: \$identityId) {\n    id\n    ...AllAccountFinancials\n    __typename\n  }\n}\n\nfragment AllAccountFinancials on Identity {\n  accounts(filter: {}, first: \$pageSize, after: \$cursor) {\n    pageInfo {\n      hasNextPage\n      endCursor\n      __typename\n    }\n    edges {\n      cursor\n      node {\n        ...AccountWithFinancials\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment AccountWithFinancials on Account {\n  ...AccountWithLink\n  ...AccountFinancials\n  __typename\n}\n\nfragment AccountWithLink on Account {\n  ...Account\n  linkedAccount {\n    ...Account\n    __typename\n  }\n  __typename\n}\n\nfragment Account on Account {\n  ...AccountCore\n  custodianAccounts {\n    ...CustodianAccount\n    __typename\n  }\n  __typename\n}\n\nfragment AccountCore on Account {\n  id\n  archivedAt\n  branch\n  closedAt\n  createdAt\n  cacheExpiredAt\n  currency\n  requiredIdentityVerification\n  unifiedAccountType\n  supportedCurrencies\n  nickname\n  status\n  accountOwnerConfiguration\n  accountFeatures {\n    ...AccountFeature\n    __typename\n  }\n  accountOwners {\n    ...AccountOwner\n    __typename\n  }\n  type\n  __typename\n}\n\nfragment AccountFeature on AccountFeature {\n  name\n  enabled\n  __typename\n}\n\nfragment AccountOwner on AccountOwner {\n  accountId\n  identityId\n  accountNickname\n  clientCanonicalId\n  accountOpeningAgreementsSigned\n  name\n  email\n  ownershipType\n  activeInvitation {\n    ...AccountOwnerInvitation\n    __typename\n  }\n  sentInvitations {\n    ...AccountOwnerInvitation\n    __typename\n  }\n  __typename\n}\n\nfragment AccountOwnerInvitation on AccountOwnerInvitation {\n  id\n  createdAt\n  inviteeName\n  inviteeEmail\n  inviterName\n  inviterEmail\n  updatedAt\n  sentAt\n  status\n  __typename\n}\n\nfragment CustodianAccount on CustodianAccount {\n  id\n  branch\n  custodian\n  status\n  updatedAt\n  __typename\n}\n\nfragment AccountFinancials on Account {\n  id\n  custodianAccounts {\n    id\n    branch\n    financials {\n      current {\n        ...CustodianAccountCurrentFinancialValues\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  financials {\n    currentCombined {\n      id\n      ...AccountCurrentFinancials\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment CustodianAccountCurrentFinancialValues on CustodianAccountCurrentFinancialValues {\n  deposits {\n    ...Money\n    __typename\n  }\n  earnings {\n    ...Money\n    __typename\n  }\n  netDeposits {\n    ...Money\n    __typename\n  }\n  netLiquidationValue {\n    ...Money\n    __typename\n  }\n  withdrawals {\n    ...Money\n    __typename\n  }\n  __typename\n}\n\nfragment Money on Money {\n  amount\n  cents\n  currency\n  __typename\n}\n\nfragment AccountCurrentFinancials on AccountCurrentFinancials {\n  id\n  netLiquidationValue {\n    ...Money\n    __typename\n  }\n  netDeposits {\n    ...Money\n    __typename\n  }\n  simpleReturns(referenceDate: \$startDate) {\n    ...SimpleReturns\n    __typename\n  }\n  totalDeposits {\n    ...Money\n    __typename\n  }\n  totalWithdrawals {\n    ...Money\n    __typename\n  }\n  __typename\n}\n\nfragment SimpleReturns on SimpleReturns {\n  amount {\n    ...Money\n    __typename\n  }\n  asOf\n  rate\n  referenceDate\n  __typename\n}";
        case 'FetchAccountFinancials': return "query FetchAccountFinancials(\$ids: [String!]!, \$startDate: Date, \$currency: Currency) {\n  accounts(ids: \$ids) {\n id\n ...AccountFinancials\n __typename\n  }\n}\n\nfragment AccountFinancials on Account {\n  id\n  custodianAccounts {\n id\n branch\n financials {\n current {\n ...CustodianAccountCurrentFinancialValues\n __typename\n }\n __typename\n }\n __typename\n  }\n  financials {\n currentCombined(currency: \$currency) {\n   id\n   ...AccountCurrentFinancials\n   __typename\n }\n __typename\n  }\n  __typename\n}\n\nfragment CustodianAccountCurrentFinancialValues on CustodianAccountCurrentFinancialValues {\n  deposits {\n ...Money\n __typename\n  }\n  earnings {\n ...Money\n __typename\n  }\n  netDeposits {\n ...Money\n __typename\n  }\n  netLiquidationValue {\n ...Money\n __typename\n  }\n  withdrawals {\n ...Money\n __typename\n  }\n  __typename\n}\n\nfragment Money on Money {\n  amount\n  cents\n  currency\n  __typename\n}\n\nfragment AccountCurrentFinancials on AccountCurrentFinancials {\n  id\n  netLiquidationValueV2 {\n ...Money\n __typename\n  }\n  netDeposits: netDepositsV2 {\n ...Money\n __typename\n  }\n  simpleReturns(referenceDate: \$startDate) {\n ...SimpleReturns\n __typename\n  }\n  totalDeposits: totalDepositsV2 {\n ...Money\n __typename\n  }\n  totalWithdrawals: totalWithdrawalsV2 {\n ...Money\n __typename\n  }\n  __typename\n}\n\nfragment SimpleReturns on SimpleReturns {\n  amount {\n ...Money\n __typename\n  }\n  asOf\n  rate\n  referenceDate\n  __typename\n}";
        case 'FetchActivityFeedItems': return "query FetchActivityFeedItems(\$first: Int, \$cursor: Cursor, \$condition: ActivityCondition, \$orderBy: [ActivitiesOrderBy!] = OCCURRED_AT_DESC) {\n  activityFeedItems(\n    first: \$first\n    after: \$cursor\n    condition: \$condition\n    orderBy: \$orderBy\n  ) {\n    edges {\n      node {\n        ...Activity\n        __typename\n      }\n      __typename\n    }\n    pageInfo {\n      hasNextPage\n      endCursor\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment Activity on ActivityFeedItem {\n  accountId\n  aftOriginatorName\n  aftTransactionCategory\n  aftTransactionType\n  amount\n  amountSign\n  assetQuantity\n  assetSymbol\n  canonicalId\n  currency\n  eTransferEmail\n  eTransferName\n  externalCanonicalId\n  identityId\n  institutionName\n  occurredAt\n  p2pHandle\n  p2pMessage\n  spendMerchant\n  securityId\n  billPayCompanyName\n  billPayPayeeNickname\n  redactedExternalAccountNumber\n  opposingAccountId\n  status\n  subType\n  type\n  strikePrice\n  contractType\n  expiryDate\n  chequeNumber\n  provisionalCreditAmount\n  primaryBlocker\n  interestRate\n  frequency\n  counterAssetSymbol\n  rewardProgram\n  counterPartyCurrency\n  counterPartyCurrencyAmount\n  counterPartyName\n  fxRate\n  fees\n  reference\n  __typename\n}";
        case 'FetchSecuritySearchResult': return "query FetchSecuritySearchResult(\$query: String!) {\n  securitySearch(input: {query: \$query}) {\n    results {\n      ...SecuritySearchResult\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment SecuritySearchResult on Security {\n  id\n  buyable\n  status\n  stock {\n    symbol\n    name\n    primaryExchange\n    __typename\n  }\n  securityGroups {\n    id\n    name\n    __typename\n  }\n  quoteV2 {\n    ... on EquityQuote {\n      marketStatus\n      __typename\n    }\n    __typename\n  }\n  __typename\n}";
        case 'FetchSecurityHistoricalQuotes': return "query FetchSecurityHistoricalQuotes(\$id: ID!, \$timerange: String! = \"1d\") {\n  security(id: \$id) {\n    id\n    historicalQuotes(timeRange: \$timerange) {\n      ...HistoricalQuote\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment HistoricalQuote on HistoricalQuote {\n  adjustedPrice\n  currency\n  date\n  securityId\n  time\n  __typename\n}";
        case 'FetchAccountsWithBalance': return "query FetchAccountsWithBalance(\$ids: [String!]!, \$type: BalanceType!) {\n  accounts(ids: \$ids) {\n    ...AccountWithBalance\n    __typename\n  }\n}\n\nfragment AccountWithBalance on Account {\n  id\n  custodianAccounts {\n    id\n    financials {\n      ... on CustodianAccountFinancialsSo {\n        balance(type: \$type) {\n          ...Balance\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment Balance on Balance {\n  quantity\n  securityId\n  __typename\n}";
        case 'FetchSecurityMarketData': return "query FetchSecurityMarketData(\$id: ID!) {\n  security(id: \$id) {\n    id\n    ...SecurityMarketData\n    __typename\n  }\n}\n\nfragment SecurityMarketData on Security {\n  id\n  allowedOrderSubtypes\n  marginRates {\n    ...MarginRates\n    __typename\n  }\n  fundamentals {\n    avgVolume\n    high52Week\n    low52Week\n    yield\n    peRatio\n    marketCap\n    currency\n    description\n    __typename\n  }\n  quote {\n    bid\n    ask\n    open\n    high\n    low\n    volume\n    askSize\n    bidSize\n    last\n    lastSize\n    quotedAsOf\n    quoteDate\n    amount\n    previousClose\n    __typename\n  }\n  stock {\n    primaryExchange\n    primaryMic\n    name\n    symbol\n    __typename\n  }\n  __typename\n}\n\nfragment MarginRates on MarginRates {\n  clientMarginRate\n  __typename\n}";
        case 'FetchFundsTransfer': return "query FetchFundsTransfer(\$id: ID!) {\n  fundsTransfer: funds_transfer(id: \$id, include_cancelled: true) {\n    ...FundsTransfer\n    __typename\n  }\n}\n\nfragment FundsTransfer on FundsTransfer {\n  id\n  status\n  cancellable\n  rejectReason: reject_reason\n  schedule {\n    id\n    __typename\n  }\n  source {\n    ...BankAccountOwner\n    __typename\n  }\n  destination {\n    ...BankAccountOwner\n    __typename\n  }\n  __typename\n}\n\nfragment BankAccountOwner on BankAccountOwner {\n  bankAccount: bank_account {\n    ...BankAccount\n    __typename\n  }\n  __typename\n}\n\nfragment BankAccount on BankAccount {\n  id\n  accountName: account_name\n  corporate\n  createdAt: created_at\n  currency\n  institutionName: institution_name\n  jurisdiction\n  nickname\n  type\n  updatedAt: updated_at\n  verificationDocuments: verification_documents {\n    ...BankVerificationDocument\n    __typename\n  }\n  verifications {\n    ...BankAccountVerification\n    __typename\n  }\n  ...CaBankAccount\n  ...UsBankAccount\n  __typename\n}\n\nfragment CaBankAccount on CaBankAccount {\n  accountName: account_name\n  accountNumber: account_number\n  __typename\n}\n\nfragment UsBankAccount on UsBankAccount {\n  accountName: account_name\n  accountNumber: account_number\n  __typename\n}\n\nfragment BankVerificationDocument on VerificationDocument {\n  id\n  acceptable\n  updatedAt: updated_at\n  createdAt: created_at\n  documentId: document_id\n  documentType: document_type\n  rejectReason: reject_reason\n  reviewedAt: reviewed_at\n  reviewedBy: reviewed_by\n  __typename\n}\n\nfragment BankAccountVerification on BankAccountVerification {\n  custodianProcessedAt: custodian_processed_at\n  custodianStatus: custodian_status\n  document {\n    ...BankVerificationDocument\n    __typename\n  }\n  __typename\n}";
        case 'FetchInstitutionalTransfer': return "query FetchInstitutionalTransfer(\$id: ID!) {\n  accountTransfer(id: \$id) {\n    ...InstitutionalTransfer\n    __typename\n  }\n}\n\nfragment InstitutionalTransfer on InstitutionalTransfer {\n  id\n  accountId: account_id\n  state\n  documentId: document_id\n  documentType: document_type\n  expectedCompletionDate: expected_completion_date\n  timelineExpectation: timeline_expectation {\n    lowerBound: lower_bound\n    upperBound: upper_bound\n    __typename\n  }\n  estimatedCompletionMaximum: estimated_completion_maximum\n  estimatedCompletionMinimum: estimated_completion_minimum\n  institutionName: institution_name\n  transferStatus: external_state\n  redactedInstitutionAccountNumber: redacted_institution_account_number\n  expectedValue: expected_value\n  transferType: transfer_type\n  cancellable\n  pdfUrl: pdf_url\n  clientVisibleState: client_visible_state\n  shortStatusDescription: short_status_description\n  longStatusDescription: long_status_description\n  progressPercentage: progress_percentage\n  type\n  rolloverType: rollover_type\n  autoSignatureEligible: auto_signature_eligible\n  parentInstitution: parent_institution {\n    id\n    name\n    __typename\n  }\n  stateHistories: state_histories {\n    id\n    state\n    notes\n    transitionSubmittedBy: transition_submitted_by\n    transitionedAt: transitioned_at\n    transitionCode: transition_code\n    __typename\n  }\n  transferFeeReimbursement: transfer_fee_reimbursement {\n    id\n    feeAmount: fee_amount\n    __typename\n  }\n  docusignSentViaEmail: docusign_sent_via_email\n  clientAccountType: client_account_type\n  primaryClientIdentityId: primary_client_identity_id\n  primaryOwnerSigned: primary_owner_signed\n  secondaryOwnerSigned: secondary_owner_signed\n  __typename\n}";
        case 'FetchAccountHistoricalFinancials': return "query FetchAccountHistoricalFinancials(\$id: ID!, \$currency: Currency!, \$startDate: Date, \$resolution: DateResolution!, \$endDate: Date, \$first: Int, \$cursor: String) {\n          account(id: \$id) {\n            id\n            financials {\n              historicalDaily(\n                currency: \$currency\n                startDate: \$startDate\n                resolution: \$resolution\n                endDate: \$endDate\n                first: \$first\n                after: \$cursor\n              ) {\n                edges {\n                  node {\n                    ...AccountHistoricalFinancials\n                    __typename\n                  }\n                  __typename\n                }\n                pageInfo {\n                  hasNextPage\n                  endCursor\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n        }\n\n        fragment AccountHistoricalFinancials on AccountHistoricalDailyFinancials {\n          date\n          netLiquidationValueV2 {\n            ...Money\n            __typename\n          }\n          netDepositsV2 {\n            ...Money\n            __typename\n          }\n          __typename\n        }\n\n        fragment Money on Money {\n          amount\n          cents\n          currency\n          __typename\n        }";
        case 'FetchAllAccounts': return "query FetchAllAccounts(\$identityId: ID!, \$filter: AccountsFilter = {}, \$pageSize: Int = 25, \$cursor: String) {\n  identity(id: \$identityId) {\n id\n ...AllAccounts\n __typename\n  }\n}\n\nfragment AllAccounts on Identity {\n  accounts(filter: \$filter, first: \$pageSize, after: \$cursor) {\n pageInfo {\n   hasNextPage\n   endCursor\n   __typename\n }\n edges {\n   cursor\n   node {\n  ...AccountWithLink\n  __typename\n   }\n   __typename\n }\n __typename\n  }\n  __typename\n}\n\nfragment AccountWithLink on Account {\n  ...Account\n  linkedAccount {\n ...Account\n __typename\n  }\n  __typename\n}\n\nfragment Account on Account {\n  ...AccountCore\n  custodianAccounts {\n ...CustodianAccount\n __typename\n  }\n  __typename\n}\n\nfragment AccountCore on Account {\n  id\n  archivedAt\n  branch\n  closedAt\n  createdAt\n  cacheExpiredAt\n  currency\n  requiredIdentityVerification\n  unifiedAccountType\n  supportedCurrencies\n  compatibleCurrencies\n  nickname\n  status\n  accountOwnerConfiguration\n  accountFeatures {\n ...AccountFeature\n __typename\n  }\n  accountOwners {\n ...AccountOwner\n __typename\n  }\n  accountEntityRelationships {\n ...AccountEntityRelationship\n __typename\n  }\n  accountUpgradeProcesses {\n ...AccountUpgradeProcess\n __typename\n  }\n  type\n  __typename\n}\n\nfragment AccountFeature on AccountFeature {\n  name\n  enabled\n  functional\n  firstEnabledOn\n  __typename\n}\n\nfragment AccountOwner on AccountOwner {\n  accountId\n  identityId\n  accountNickname\n  clientCanonicalId\n  accountOpeningAgreementsSigned\n  name\n  email\n  ownershipType\n  activeInvitation {\n ...AccountOwnerInvitation\n __typename\n  }\n  sentInvitations {\n ...AccountOwnerInvitation\n __typename\n  }\n  __typename\n}\n\nfragment AccountOwnerInvitation on AccountOwnerInvitation {\n  id\n  createdAt\n  inviteeName\n  inviteeEmail\n  inviterName\n  inviterEmail\n  updatedAt\n  sentAt\n  status\n  __typename\n}\n\nfragment AccountEntityRelationship on AccountEntityRelationship {\n  accountCanonicalId\n  entityCanonicalId\n  entityOwnershipType\n  entityType\n  __typename\n}\n\nfragment AccountUpgradeProcess on AccountUpgradeProcess {\n  canonicalId\n  status\n  targetAccountType\n  __typename\n}\n\nfragment CustodianAccount on CustodianAccount {\n  id\n  branch\n  custodian\n  status\n  updatedAt\n  __typename\n}";
        case 'FetchIdentityHistoricalFinancials': return "query FetchIdentityHistoricalFinancials(\$identityId: ID!, \$currency: Currency!, \$startDate: Date, \$endDate: Date, \$first: Int, \$cursor: String, \$accountIds: [ID!]) {\n      identity(id: \$identityId) {\n        id\n        financials(filter: {accounts: \$accountIds}) {\n          historicalDaily(\n            currency: \$currency\n            startDate: \$startDate\n            endDate: \$endDate\n            first: \$first\n            after: \$cursor\n          ) {\n            edges {\n              node {\n                ...IdentityHistoricalFinancials\n                __typename\n              }\n              __typename\n            }\n            pageInfo {\n              hasNextPage\n              endCursor\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n    }\n\n    fragment IdentityHistoricalFinancials on IdentityHistoricalDailyFinancials {\n      date\n      netLiquidationValueV2 {\n        amount\n        currency\n        __typename\n      }\n      netDepositsV2 {\n        amount\n        currency\n        __typename\n      }\n      __typename\n    }";
        };
    }

    private $accounts_cache = [];
    public function getAccounts(bool $open_only = TRUE, bool $use_cache = TRUE): array {
        $cache_key = $open_only ? 'open' : 'all';
        if (!isset($accounts_cache[$cache_key]) || !$use_cache) {
            $accounts = $this->doGraphQLQuery(
                'FetchAllAccountFinancials',
                [
                    'pageSize' => 25,
                    'identityId' => $this->getTokenInfo()->identity_canonical_id,
                ],
                'identity.accounts.edges',
                'array',
                $open_only ? fn($account) => $account->status === 'open' : NULL,
                LOAD_ALL_PAGES,
            );
            array_walk($accounts, fn($account) => $this->_accountAddDescription($account));
            $accounts_cache[$cache_key] = $accounts;
        }
        return $accounts_cache[$cache_key];
    }

    private function _accountAddDescription($account) {
        $account->number = $account->id;
        // This is the account number visible in the WS app:
        foreach ($account->custodianAccounts as $ca) {
            if (($ca->branch === 'WS' || $ca->branch === 'TR') && $ca->status === 'open') {
                $account->number = $ca->id;
            }
        }

        $account->description = $account->unifiedAccountType;
        if (!empty($account->nickname)) {
            $account->description = $account->nickname;
        } elseif ($account->unifiedAccountType === 'CASH') {
            if ($account->accountOwnerConfiguration === 'MULTI_OWNER') {
                $account->description = "Cash: joint";
            } else {
                $account->description = "Cash";
            }
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_RRSP') {
            $account->description = "RRSP: self-directed - $account->currency";
        } elseif ($account->unifiedAccountType === 'MANAGED_RRSP') {
            $account->description = "RRSP: managed - $account->currency";
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_SPOUSAL_RRSP') {
            $account->description = "RRSP: self-directed spousal - $account->currency";
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_TFSA') {
            $account->description = "TFSA: self-directed - $account->currency";
        } elseif ($account->unifiedAccountType === 'MANAGED_TFSA') {
            $account->description = "TFSA: managed - $account->currency";
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_JOINT_NON_REGISTERED') {
            $account->description = "Non-registered: self-directed - joint";
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_NON_REGISTERED_MARGIN') {
            $account->description = "Non-registered: self-directed margin";
        } elseif ($account->unifiedAccountType === 'MANAGED_JOINT') {
            $account->description = "Non-registered: managed - joint";
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_CRYPTO') {
            $account->description = "Crypto";
        } elseif ($account->unifiedAccountType === 'SELF_DIRECTED_RRIF') {
            $account->description = "RRIF: self-directed - $account->currency";
        }
        // @TODO Add other types
    }

    public function getAccountBalances(string $account_id) : array {
        $accounts = $this->doGraphQLQuery(
            'FetchAccountsWithBalance',
            [
                'type' => 'TRADING',
                'ids'  => [$account_id],
            ],
            'accounts',
            'array',
        );

        $balances = [];
        foreach ($accounts[0]->custodianAccounts as $ca) {
            foreach ($ca->financials->balance as $bal) {
                $security = $bal->securityId;
                if ($security !== 'sec-c-cad' && $security !== 'sec-c-usd') {
                    $security = $this->securityIdToSymbol($security);
                }
                $balances[$security] = $bal->quantity;
            }
        }
        return $balances;
    }

    public function getAccountHistoricalFinancials(string $account_id, string $currency, string $start_date = NULL, string $end_date = NULL, string $resolution = 'WEEKLY', int $first = NULL, string $cursor = NULL): array {
        return $this->doGraphQLQuery(
            'FetchAccountHistoricalFinancials',
            [
                'id' => $account_id,
                'currency' => $currency,
                'startDate' => $start_date,
                'endDate' => $end_date,
                'resolution' => $resolution,
                'first' => $first,
                'cursor' => $cursor,
            ],
            'account.financials.historicalDaily.edges',
            'array'
        );
    }

    public function getIdentityHistoricalFinancials(array $account_ids = [], string $currency = 'CAD', string $start_date = NULL, string $end_date = NULL, int $first = NULL, string $cursor = NULL): array {
        return $this->doGraphQLQuery(
            'FetchIdentityHistoricalFinancials',
            [
                'identityId' => $this->getTokenInfo()->identity_canonical_id,
                'currency' => $currency,
                'startDate' => $start_date,
                'endDate' => $end_date,
                'first' => $first,
                'cursor' => $cursor,
                'accountIds' => $account_ids,
            ],
            'identity.financials.historicalDaily.edges',
            'array',
            NULL,
        );
    }

    public function getActivities(string $account_id, int $how_many = 50, string $order_by = 'OCCURRED_AT_DESC', bool $ignore_rejected = TRUE, string $startDate = NULL, string $endDate = NULL): array {
        $activities = $this->doGraphQLQuery(
            'FetchActivityFeedItems',
            [
                'orderBy' => $order_by,
                'first'   => $how_many,
                'condition' => [
                    'startDate'  => $startDate ? date('Y-m-d\TH:i:s.999\Z', strtotime($startDate)) : NULL,
                    'endDate'    => date('Y-m-d\TH:i:s.999\Z', $endDate ? strtotime($endDate) : strtotime('+1 day')),
                    'accountIds' => [$account_id],
                ],
            ],
            'activityFeedItems.edges',
            'array',
            fn($act) => $act->type != 'LEGACY_TRANSFER' && (!$ignore_rejected || empty($act->status) || (!string_contains($act->status, 'rejected') && !string_contains($act->status, 'cancelled'))),
            LOAD_ALL_PAGES
        );
        array_walk($activities, fn($act) => $this->_activityAddDescription($act));
        return $activities;
    }

    private function _activityAddDescription(&$act) {
        $act->description = "$act->type: $act->subType";
        if ($act->type === 'INTERNAL_TRANSFER') {
            $accounts = $this->getAccounts(FALSE);
            $matching = array_filter($accounts, fn($acc) => $acc->id === $act->opposingAccountId);
            $target_account = array_pop($matching);
            if ($target_account) {
                $account_description = "$target_account->description ($target_account->number)";
            } else {
                $account_description = $act->opposingAccountId;
            }
            if ($act->subType === 'SOURCE') {
                $act->description = "Transfer out: Transfer to Wealthsimple $account_description";
            } else {
                $act->description = "Transfer in: Transfer from Wealthsimple $account_description";
            }
        } elseif ($act->type === 'DIY_BUY' || $act->type === 'DIY_SELL') {
            $verb = ucfirst(strtolower(str_replace('_', ' ', $act->subType)));
            $action = $act->type === 'DIY_BUY' ? 'buy' : 'sell';
            $security = $this->securityIdToSymbol($act->securityId);
            $act->description = "$verb: $action " . ((float) $act->assetQuantity) . " x $security @ " . ($act->amount / $act->assetQuantity);
        } elseif (($act->type === 'DEPOSIT' || $act->type === 'WITHDRAWAL') && ($act->subType === 'E_TRANSFER' || $act->subType === 'E_TRANSFER_FUNDING')) {
            $direction = $act->type === 'WITHDRAWAL' ? 'to' : 'from';
            $act->description = ucfirst(strtolower($act->type)) . ": Interac e-transfer $direction $act->eTransferName $act->eTransferEmail";
        } elseif ($act->type === 'DEPOSIT' && $act->subType === 'PAYMENT_CARD_TRANSACTION') {
            $act->description = ucfirst(strtolower($act->type)) . ": Debit card funding";
        } elseif ($act->subType === 'EFT') {
            $details = $this->getETFDetails($act->externalCanonicalId);
            $type = ucfirst(strtolower($act->type));
            $direction = $act->type === 'DEPOSIT' ? 'from' : 'to';
            $prop = $act->type === 'DEPOSIT' ? 'source' : 'destination';
            $bank_account = $details->{$prop}->bankAccount;
            $act->description = "$type: EFT $direction " . ($bank_account->nickname ?? $bank_account->accountName) . " {$bank_account->accountNumber}";
        } elseif ($act->type === 'REFUND' && $act->subType === 'TRANSFER_FEE_REFUND') {
            $act->description = "Reimbursement: account transfer fee";
        } elseif ($act->type === 'INSTITUTIONAL_TRANSFER_INTENT' && $act->subType === 'TRANSFER_IN') {
            $details = $this->getTransferDetails($act->externalCanonicalId);
            $verb = ucfirst(strtolower(str_replace('_', '-', $details->transferType)));
            $act->description = "Institutional transfer: $verb " . strtoupper($details->clientAccountType) . " account transfer from $details->institutionName ****$details->redactedInstitutionAccountNumber";
        } elseif ($act->type === 'INTEREST') {
            if ($act->subType === 'FPL_INTEREST') {
                $act->description = "Stock Lending Earnings";
            } else {
                $act->description = "Interest";
            }
        } elseif ($act->type === 'DIVIDEND') {
            $security = $this->securityIdToSymbol($act->securityId);
            $act->description = "Dividend: $security";
        } elseif ($act->type === 'FUNDS_CONVERSION') {
            $act->description = "Funds converted: $act->currency from " . ($act->currency === 'CAD' ? 'USD' : 'CAD');
        } elseif ($act->type === 'NON_RESIDENT_TAX') {
            $act->description = "Non-resident tax";
        } elseif (($act->type === 'DEPOSIT' || $act->type === 'WITHDRAWAL') && $act->subType === 'AFT') {
            // Refs:
            //   https://www.payments.ca/payment-resources/iso-20022/automatic-funds-transfer
            //   https://www.payments.ca/compelling-new-evidence-strong-link-between-aft-and-canadas-cheque-decline
            // 2nd ref states: "AFTs are electronic direct credit or direct debit transactions, commonly known in Canada as direct deposits or pre-authorized debits (PADs)."
            $type = $act->type === 'DEPOSIT' ? 'Direct deposit' : 'Pre-authorized debit';
            $direction = $type === 'Direct deposit' ? 'from' : 'to';
            $institution = !empty($act->aftOriginatorName) ? $act->aftOriginatorName : $act->externalCanonicalId;
            $act->description = "$type: $direction $institution";
        } elseif ($act->type === 'WITHDRAWAL' && $act->subType === 'BILL_PAY') {
            $name = !empty($act->billPayPayeeNickname) ? $act->billPayPayeeNickname : $act->billPayCompanyName;
            $number = $act->redactedExternalAccountNumber;
            $act->description = "Withdrawal: Bill pay $name $number";
        } elseif ($act->type === 'P2P_PAYMENT' && ($act->subType === 'SEND' || $act->subType === 'SEND_RECEIVED')) {
            $direction = $act->subType === 'SEND' ? 'sent to' : 'received from';
            $p2pHandle = $act->p2pHandle;
            $act->description = "Cash $direction $p2pHandle";
        } elseif ($act->type === 'PROMOTION' && $act->subType === 'INCENTIVE_BONUS') {
            $subtype = ucfirst(strtolower(str_replace('_', ' ', $act->subType)));
            $act->description = "Promotion: $subtype";
        } elseif ($act->type === 'REFERRAL') {
            $act->description = "Referral";
        }
        // @TODO Add other types
    }

    protected function securityIdToSymbol(string $security_id): string {
        $security_symbol = "[$security_id]";
        if ($this->security_market_data_cache_getter) {
            $market_data = $this->getSecurityMarketData($security_id);
            if (!empty($market_data->stock)) {
                $stock = $market_data->stock;
                $security_symbol = "$stock->primaryExchange:$stock->symbol";
            }
        }
        return $security_symbol;
    }

    public function getETFDetails(string $funding_id): object {
        return $this->doGraphQLQuery(
            'FetchFundsTransfer',
            [
                'id' => $funding_id,
            ],
            'fundsTransfer',
            'object',
        );
    }

    public function getTransferDetails(string $transfer_id): object {
        return $this->doGraphQLQuery(
            'FetchInstitutionalTransfer',
            [
                'id' => $transfer_id,
            ],
            'accountTransfer',
            'object',
        );
    }

    private ?\closure $security_market_data_cache_setter = NULL;
    private ?\closure $security_market_data_cache_getter = NULL;
    public function setSecurityMarketDataCache($security_market_data_cache_getter, $security_market_data_cache_setter) : void {
        $this->security_market_data_cache_getter = $security_market_data_cache_getter;
        $this->security_market_data_cache_setter = $security_market_data_cache_setter;
    }

    public function getSecurityMarketData(string $security_id, bool $use_cache = TRUE): object {
        if (empty($this->security_market_data_cache_getter) || empty($this->security_market_data_cache_setter)) {
            $use_cache = FALSE;
        }
        if ($use_cache) {
            $fn = $this->security_market_data_cache_getter;
            $cached_value = $fn($security_id);
            if ($cached_value) {
                return $cached_value;
            }
        }
        $value = $this->doGraphQLQuery(
            'FetchSecurityMarketData',
            ['id' => $security_id],
            'security',
            'object',
        );
        if ($use_cache) {
            $fn = $this->security_market_data_cache_setter;
            $value = $fn($security_id, $value);
        }
        return $value;
    }

    public function searchSecurity(string $query): array {
        return $this->doGraphQLQuery(
            'FetchSecuritySearchResult',
            ['query' => $query],
            'securitySearch.results',
            'array',
        );
    }

    /**
     * Get historical quotes for a security.
     *
     * @param string $security_id Wealthsimple security ID, from searchSecurity() response
     * @param string $time_range  eg. 1m
     *
     * @return object[]
     * @throws WSApiException
     */
    public function getSecurityHistoricalQuotes(string $security_id, string $time_range = '1m'): array {
        return $this->doGraphQLQuery(
            'FetchSecurityHistoricalQuotes',
            [
                'id' => $security_id,
                'timerange' => $time_range,
            ],
            'security.historicalQuotes',
            'array',
        );
    }
}
