<?php

namespace PPFinances\Wealthsimple;

use PPFinances\Wealthsimple\Exceptions\WSApiException;

function string_contains(?string $haystack, string $needle): bool {
    return stripos($haystack ?? '', $needle) !== FALSE;
}
function array_any(array $arr, callable $callable) {
    return count(array_filter($arr, $callable)) > 0;
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
        case 'FetchSecurityMarketData': return "query FetchSecurityMarketData(\$id: ID!) {\n  security(id: \$id) {\n    id\n    ...SecurityMarketData\n    __typename\n  }\n}\n\nfragment SecurityMarketData on Security {\n  id\n  allowedOrderSubtypes\n  marginRates {\n    ...ClientMarginRates\n    __typename\n  }\n  managementExpenseRatio\n  fundamentals {\n    ...Fundamentals\n    __typename\n  }\n  stock {\n    ...Stock\n    __typename\n  }\n  __typename\n}\n\nfragment Fundamentals on Fundamentals {\n  avgVolume\n  beta\n  circulatingSupply\n  companyCash\n  companyCeo\n  companyDebt\n  companyEarningsGrowth\n  companyGrossProfitMargin\n  companyHqLocation\n  companyRevenue\n  currency\n  dailyVolume\n  description\n  eps\n  high52Week\n  inceptionYear\n  low52Week\n  marketCap\n  numberOfEmployees\n  peRatio\n  sharesOutstanding\n  totalAssets\n  totalSupply\n  yield\n  __typename\n}\n\nfragment Stock on Stock {\n  description\n  dividendFrequency\n  ipoState\n  leverageRatio\n  name\n  primaryExchange\n  primaryMic\n  segmentMic\n  symbol\n  usPtp\n  __typename\n}\n\nfragment ClientMarginRates on MarginRates {\n  clientMarginRate\n  __typename\n}";
        case 'FetchFundsTransfer': return "query FetchFundsTransfer(\$id: ID!) {\n  fundsTransfer: funds_transfer(id: \$id, include_cancelled: true) {\n    ...FundsTransfer\n    __typename\n  }\n}\n\nfragment FundsTransfer on FundsTransfer {\n  id\n  status\n  cancellable\n  rejectReason: reject_reason\n  schedule {\n    id\n    __typename\n  }\n  source {\n    ...BankAccountOwner\n    __typename\n  }\n  destination {\n    ...BankAccountOwner\n    __typename\n  }\n  __typename\n}\n\nfragment BankAccountOwner on BankAccountOwner {\n  bankAccount: bank_account {\n    ...BankAccount\n    __typename\n  }\n  __typename\n}\n\nfragment BankAccount on BankAccount {\n  id\n  accountName: account_name\n  corporate\n  createdAt: created_at\n  currency\n  institutionName: institution_name\n  jurisdiction\n  nickname\n  type\n  updatedAt: updated_at\n  verificationDocuments: verification_documents {\n    ...BankVerificationDocument\n    __typename\n  }\n  verifications {\n    ...BankAccountVerification\n    __typename\n  }\n  ...CaBankAccount\n  ...UsBankAccount\n  __typename\n}\n\nfragment CaBankAccount on CaBankAccount {\n  accountName: account_name\n  accountNumber: account_number\n  __typename\n}\n\nfragment UsBankAccount on UsBankAccount {\n  accountName: account_name\n  accountNumber: account_number\n  __typename\n}\n\nfragment BankVerificationDocument on VerificationDocument {\n  id\n  acceptable\n  updatedAt: updated_at\n  createdAt: created_at\n  documentId: document_id\n  documentType: document_type\n  rejectReason: reject_reason\n  reviewedAt: reviewed_at\n  reviewedBy: reviewed_by\n  __typename\n}\n\nfragment BankAccountVerification on BankAccountVerification {\n  custodianProcessedAt: custodian_processed_at\n  custodianStatus: custodian_status\n  document {\n    ...BankVerificationDocument\n    __typename\n  }\n  __typename\n}";
        case 'FetchInstitutionalTransfer': return "query FetchInstitutionalTransfer(\$id: ID!) {\n  accountTransfer(id: \$id) {\n    ...InstitutionalTransfer\n    __typename\n  }\n}\n\nfragment InstitutionalTransfer on InstitutionalTransfer {\n  id\n  accountId: account_id\n  state\n  documentId: document_id\n  documentType: document_type\n  expectedCompletionDate: expected_completion_date\n  timelineExpectation: timeline_expectation {\n    lowerBound: lower_bound\n    upperBound: upper_bound\n    __typename\n  }\n  estimatedCompletionMaximum: estimated_completion_maximum\n  estimatedCompletionMinimum: estimated_completion_minimum\n  institutionName: institution_name\n  transferStatus: external_state\n  redactedInstitutionAccountNumber: redacted_institution_account_number\n  expectedValue: expected_value\n  transferType: transfer_type\n  cancellable\n  pdfUrl: pdf_url\n  clientVisibleState: client_visible_state\n  shortStatusDescription: short_status_description\n  longStatusDescription: long_status_description\n  progressPercentage: progress_percentage\n  type\n  rolloverType: rollover_type\n  autoSignatureEligible: auto_signature_eligible\n  parentInstitution: parent_institution {\n    id\n    name\n    __typename\n  }\n  stateHistories: state_histories {\n    id\n    state\n    notes\n    transitionSubmittedBy: transition_submitted_by\n    transitionedAt: transitioned_at\n    transitionCode: transition_code\n    __typename\n  }\n  transferFeeReimbursement: transfer_fee_reimbursement {\n    id\n    feeAmount: fee_amount\n    __typename\n  }\n  docusignSentViaEmail: docusign_sent_via_email\n  clientAccountType: client_account_type\n  primaryClientIdentityId: primary_client_identity_id\n  primaryOwnerSigned: primary_owner_signed\n  secondaryOwnerSigned: secondary_owner_signed\n  __typename\n}";
        case 'FetchAccountHistoricalFinancials': return "query FetchAccountHistoricalFinancials(\$id: ID!, \$currency: Currency!, \$startDate: Date, \$resolution: DateResolution!, \$endDate: Date, \$first: Int, \$cursor: String) {\n          account(id: \$id) {\n            id\n            financials {\n              historicalDaily(\n                currency: \$currency\n                startDate: \$startDate\n                resolution: \$resolution\n                endDate: \$endDate\n                first: \$first\n                after: \$cursor\n              ) {\n                edges {\n                  node {\n                    ...AccountHistoricalFinancials\n                    __typename\n                  }\n                  __typename\n                }\n                pageInfo {\n                  hasNextPage\n                  endCursor\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n        }\n\n        fragment AccountHistoricalFinancials on AccountHistoricalDailyFinancials {\n          date\n          netLiquidationValueV2 {\n            ...Money\n            __typename\n          }\n          netDepositsV2 {\n            ...Money\n            __typename\n          }\n          __typename\n        }\n\n        fragment Money on Money {\n          amount\n          cents\n          currency\n          __typename\n        }";
        case 'FetchAllAccounts': return "query FetchAllAccounts(\$identityId: ID!, \$filter: AccountsFilter = {}, \$pageSize: Int = 25, \$cursor: String) {\n  identity(id: \$identityId) {\n id\n ...AllAccounts\n __typename\n  }\n}\n\nfragment AllAccounts on Identity {\n  accounts(filter: \$filter, first: \$pageSize, after: \$cursor) {\n pageInfo {\n   hasNextPage\n   endCursor\n   __typename\n }\n edges {\n   cursor\n   node {\n  ...AccountWithLink\n  __typename\n   }\n   __typename\n }\n __typename\n  }\n  __typename\n}\n\nfragment AccountWithLink on Account {\n  ...Account\n  linkedAccount {\n ...Account\n __typename\n  }\n  __typename\n}\n\nfragment Account on Account {\n  ...AccountCore\n  custodianAccounts {\n ...CustodianAccount\n __typename\n  }\n  __typename\n}\n\nfragment AccountCore on Account {\n  id\n  archivedAt\n  branch\n  closedAt\n  createdAt\n  cacheExpiredAt\n  currency\n  requiredIdentityVerification\n  unifiedAccountType\n  supportedCurrencies\n  compatibleCurrencies\n  nickname\n  status\n  accountOwnerConfiguration\n  accountFeatures {\n ...AccountFeature\n __typename\n  }\n  accountOwners {\n ...AccountOwner\n __typename\n  }\n  accountEntityRelationships {\n ...AccountEntityRelationship\n __typename\n  }\n  accountUpgradeProcesses {\n ...AccountUpgradeProcess\n __typename\n  }\n  type\n  __typename\n}\n\nfragment AccountFeature on AccountFeature {\n  name\n  enabled\n  functional\n  firstEnabledOn\n  __typename\n}\n\nfragment AccountOwner on AccountOwner {\n  accountId\n  identityId\n  accountNickname\n  clientCanonicalId\n  accountOpeningAgreementsSigned\n  name\n  email\n  ownershipType\n  activeInvitation {\n ...AccountOwnerInvitation\n __typename\n  }\n  sentInvitations {\n ...AccountOwnerInvitation\n __typename\n  }\n  __typename\n}\n\nfragment AccountOwnerInvitation on AccountOwnerInvitation {\n  id\n  createdAt\n  inviteeName\n  inviteeEmail\n  inviterName\n  inviterEmail\n  updatedAt\n  sentAt\n  status\n  __typename\n}\n\nfragment AccountEntityRelationship on AccountEntityRelationship {\n  accountCanonicalId\n  entityCanonicalId\n  entityOwnershipType\n  entityType\n  __typename\n}\n\nfragment AccountUpgradeProcess on AccountUpgradeProcess {\n  canonicalId\n  status\n  targetAccountType\n  __typename\n}\n\nfragment CustodianAccount on CustodianAccount {\n  id\n  branch\n  custodian\n  status\n  updatedAt\n  __typename\n}";
        case 'FetchIdentityHistoricalFinancials': return "query FetchIdentityHistoricalFinancials(\$identityId: ID!, \$currency: Currency!, \$startDate: Date, \$endDate: Date, \$first: Int, \$cursor: String, \$accountIds: [ID!]) {\n      identity(id: \$identityId) {\n        id\n        financials(filter: {accounts: \$accountIds}) {\n          historicalDaily(\n            currency: \$currency\n            startDate: \$startDate\n            endDate: \$endDate\n            first: \$first\n            after: \$cursor\n          ) {\n            edges {\n              node {\n                ...IdentityHistoricalFinancials\n                __typename\n              }\n              __typename\n            }\n            pageInfo {\n              hasNextPage\n              endCursor\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n    }\n\n    fragment IdentityHistoricalFinancials on IdentityHistoricalDailyFinancials {\n      date\n      netLiquidationValueV2 {\n        amount\n        currency\n        __typename\n      }\n      netDepositsV2 {\n        amount\n        currency\n        __typename\n      }\n      __typename\n    }";
        case 'FetchCorporateActionChildActivities': return "query FetchCorporateActionChildActivities(\$activityCanonicalId: String!) {\n  corporateActionChildActivities(\n    condition: {activityCanonicalId: \$activityCanonicalId}\n  ) {\n    nodes {\n      ...CorporateActionChildActivity\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment CorporateActionChildActivity on CorporateActionChildActivity {\n  canonicalId\n  activityCanonicalId\n  assetName\n  assetSymbol\n  assetType\n  entitlementType\n  quantity\n  currency\n  price\n  recordDate\n  __typename\n}";
        case 'FetchBrokerageMonthlyStatementTransactions': return "query FetchBrokerageMonthlyStatementTransactions(\$period: String!, \$accountId: String!) {\n  brokerageMonthlyStatements(period: \$period, accountId: \$accountId) {\n    id\n    statementType\n    createdAt\n    data {\n      ... on BrokerageMonthlyStatementObject {\n        ...BrokerageMonthlyStatementObject\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment BrokerageMonthlyStatementObject on BrokerageMonthlyStatementObject {\n  custodianAccountId\n  activitiesPerCurrency {\n    currency\n    currentTransactions {\n      ...BrokerageMonthlyStatementTransactions\n      __typename\n    }\n    __typename\n  }\n  currentTransactions {\n    ...BrokerageMonthlyStatementTransactions\n    __typename\n  }\n  isMultiCurrency\n  __typename\n}\n\nfragment BrokerageMonthlyStatementTransactions on BrokerageMonthlyStatementTransactions {\n  balance\n  cashMovement\n  unit\n  description\n  transactionDate\n  transactionType\n  __typename\n}";
        case 'FetchIdentityPositions': return "query FetchIdentityPositions(\$identityId: ID!, \$currency: Currency!, \$first: Int, \$cursor: String, \$accountIds: [ID!], \$aggregated: Boolean, \$currencyOverride: CurrencyOverride, \$sort: PositionSort, \$sortDirection: PositionSortDirection, \$filter: PositionFilter, \$since: PointInTime, \$includeSecurity: Boolean = false, \$includeAccountData: Boolean = false, \$includeOneDayReturnsBaseline: Boolean = false) {\n  identity(id: \$identityId) {\n    id\n    financials(filter: {accounts: \$accountIds}) {\n      current(currency: \$currency) {\n        id\n        positions(\n          first: \$first\n          after: \$cursor\n          aggregated: \$aggregated\n          filter: \$filter\n          sort: \$sort\n          sortDirection: \$sortDirection\n        ) {\n          edges {\n            node {\n              ...PositionV2\n              __typename\n            }\n            __typename\n          }\n          pageInfo {\n            hasNextPage\n            endCursor\n            __typename\n          }\n          totalCount\n          status\n          hasOptionsPosition\n          hasCryptoPositionsOnly\n          securityTypes\n          securityCurrencies\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment SecuritySummary on Security {\n  ...SecuritySummaryDetails\n  stock {\n    ...StockSummary\n    __typename\n  }\n  quoteV2(currency: null) {\n    ...SecurityQuoteV2\n    __typename\n  }\n  optionDetails {\n    ...OptionSummary\n    __typename\n  }\n  __typename\n}\n\nfragment SecuritySummaryDetails on Security {\n  id\n  currency\n  inactiveDate\n  status\n  wsTradeEligible\n  equityTradingSessionType\n  securityType\n  active\n  securityGroups {\n    id\n    name\n    __typename\n  }\n  features\n  logoUrl\n  __typename\n}\n\nfragment StockSummary on Stock {\n  name\n  symbol\n  primaryMic\n  primaryExchange\n  __typename\n}\n\nfragment StreamedSecurityQuoteV2 on UnifiedQuote {\n  __typename\n  securityId\n  ask\n  bid\n  currency\n  price\n  sessionPrice\n  quotedAsOf\n  ... on EquityQuote {\n    marketStatus\n    askSize\n    bidSize\n    close\n    high\n    last\n    lastSize\n    low\n    open\n    mid\n    volume: vol\n    referenceClose\n    __typename\n  }\n  ... on OptionQuote {\n    marketStatus\n    askSize\n    bidSize\n    close\n    high\n    last\n    lastSize\n    low\n    open\n    mid\n    volume: vol\n    breakEven\n    inTheMoney\n    liquidityStatus\n    openInterest\n    underlyingSpot\n    __typename\n  }\n}\n\nfragment SecurityQuoteV2 on UnifiedQuote {\n  ...StreamedSecurityQuoteV2\n  previousBaseline\n  __typename\n}\n\nfragment OptionSummary on Option {\n  underlyingSecurity {\n    ...UnderlyingSecuritySummary\n    __typename\n  }\n  maturity\n  osiSymbol\n  expiryDate\n  multiplier\n  optionType\n  strikePrice\n  __typename\n}\n\nfragment UnderlyingSecuritySummary on Security {\n  id\n  stock {\n    name\n    primaryExchange\n    primaryMic\n    symbol\n    __typename\n  }\n  __typename\n}\n\nfragment PositionLeg on PositionLeg {\n  security {\n    id\n    ...SecuritySummary @include(if: \$includeSecurity)\n    __typename\n  }\n  quantity\n  positionDirection\n  bookValue {\n    amount\n    currency\n    __typename\n  }\n  totalValue(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  averagePrice {\n    amount\n    currency\n    __typename\n  }\n  percentageOfAccount\n  unrealizedReturns(since: \$since) {\n    amount\n    currency\n    __typename\n  }\n  marketAveragePrice: averagePrice(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  marketBookValue: bookValue(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  marketUnrealizedReturns: unrealizedReturns(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  oneDayReturnsBaselineV2(currencyOverride: \$currencyOverride) @include(if: \$includeOneDayReturnsBaseline) {\n    baseline {\n      currency\n      amount\n      __typename\n    }\n    useDailyPriceChange\n    __typename\n  }\n  __typename\n}\n\nfragment PositionV2 on PositionV2 {\n  id\n  quantity\n  accounts @include(if: \$includeAccountData) {\n    id\n    __typename\n  }\n  percentageOfAccount\n  positionDirection\n  bookValue {\n    amount\n    currency\n    __typename\n  }\n  averagePrice {\n    amount\n    currency\n    __typename\n  }\n  marketAveragePrice: averagePrice(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  marketBookValue: bookValue(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  totalValue(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  unrealizedReturns(since: \$since) {\n    amount\n    currency\n    __typename\n  }\n  marketUnrealizedReturns: unrealizedReturns(currencyOverride: \$currencyOverride) {\n    amount\n    currency\n    __typename\n  }\n  security {\n    id\n    ...SecuritySummary @include(if: \$includeSecurity)\n    __typename\n  }\n  oneDayReturnsBaselineV2(currencyOverride: \$currencyOverride) @include(if: \$includeOneDayReturnsBaseline) {\n    baseline {\n      currency\n      amount\n      __typename\n    }\n    useDailyPriceChange\n    __typename\n  }\n  strategyType\n  legs {\n    ...PositionLeg\n    __typename\n  }\n  __typename\n}";
        case 'FetchCreditCardAccount': return "query FetchCreditCardAccount(\$id: ID!) {\n  creditCardAccount(id: \$id) {\n    ...CreditCardAccount\n    __typename\n  }\n}\n\nfragment CreditCardAccount on CreditCardAccount {\n  id\n  creditLimit\n  upgradesInProgress\n  balance {\n    current\n    outstanding\n    availableCreditLimit\n    pending\n    __typename\n  }\n  cardProductId\n  statementDayOfMonth\n  actions\n  currentCards {\n    id\n    actions\n    cardNumber\n    cardStatus\n    cardVariant\n    isLocked\n    isPhysicalCardActivated\n    isSupplementaryCard\n    nameOnCard\n    __typename\n  }\n  cards {\n    id\n    cardNumber\n    adminActions\n    lastPinCounterResetAt\n    isBlocked\n    isPhysicalCardActivated\n    __typename\n  }\n  preferences {\n    cardRewardRedemptionType\n    __typename\n  }\n  __typename\n}";
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

    // Mapping of account types to human-readable descriptions
    const ACCOUNT_TYPE_DESCRIPTIONS = [
        "SELF_DIRECTED_RRSP" => "RRSP: self-directed",
        "MANAGED_RRSP" => "RRSP: managed",
        "SELF_DIRECTED_SPOUSAL_RRSP" => "RRSP: self-directed spousal",
        "MANAGED_SPOUSAL_RRSP" => "RRSP: managed spousal",
        "SELF_DIRECTED_TFSA" => "TFSA: self-directed",
        "MANAGED_TFSA" => "TFSA: managed",
        "SELF_DIRECTED_FHSA" => "FHSA: self-directed",
        "MANAGED_FHSA" => "FHSA: managed",
        "SELF_DIRECTED_NON_REGISTERED" => "Non-registered: self-directed",
        "SELF_DIRECTED_JOINT_NON_REGISTERED" => "Non-registered: self-directed - joint",
        "SELF_DIRECTED_NON_REGISTERED_MARGIN" => "Non-registered: self-directed margin",
        "MANAGED_JOINT" => "Non-registered: managed - joint",
        "SELF_DIRECTED_CRYPTO" => "Crypto",
        "SELF_DIRECTED_RRIF" => "RRIF: self-directed",
        "SELF_DIRECTED_SPOUSAL_RRIF" => "RRIF: self-directed spousal",
        "CREDIT_CARD" => "Credit card",
        "SELF_DIRECTED_LIRA" => "LIRA: self-directed",
        "MANAGED_LIRA" => "LIRA: managed",
        "MANAGED_FIXED_INCOME_NON_REGISTERED" => "Income Portfolio: managed",
        "PORTFOLIO_LINE_OF_CREDIT" => "Portfolio line of credit",
    ];

    private function _accountAddDescription($account) {
        $account->number = $account->id;
        // This is the account number visible in the WS app:
        foreach ($account->custodianAccounts as $ca) {
            if (($ca->branch === 'WS' || $ca->branch === 'TR') && $ca->status === 'open') {
                $account->number = $ca->id;
            }
        }

        $accountType = $account->unifiedAccountType;

        if (!empty($account->nickname)) {
            // Special case: user-defined name
            $account->description = $account->nickname;
        } elseif ($accountType === 'CASH') {
            // Special case: CASH depends on owner configuration
            $account->description = $account->accountOwnerConfiguration === 'MULTI_OWNER'
                ? "Cash: joint"
                : "Cash";
        } elseif ($accountType === 'MANAGED_NON_REGISTERED') {
            // Special case: MANAGED_NON_REGISTERED depends on features
            $features = array_column($account->accountFeatures, 'name');
            if (in_array('PRIVATE_CREDIT', $features)) {
                $account->description = "Non-registered: managed - private credit";
            } elseif (in_array('PRIVATE_EQUITY', $features)) {
                $account->description = "Non-registered: managed - private equity";
            } elseif (in_array('MANAGED', $features)) {
                $account->description = "Non-registered: managed";
            } else {
                $account->description = $accountType;
            }
        } else {
            // Simple lookup for all other types
            $account->description = static::ACCOUNT_TYPE_DESCRIPTIONS[$accountType] ?? $accountType;
        }
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

    /**
     * Retrieve activities for a specific account or list of accounts.
     *
     * @param string|string[] $account_id      The account ID or list of account IDs to retrieve activities for.
     * @param int             $how_many        The maximum number of activities to retrieve.
     * @param string          $order_by        The order in which to sort the activities.
     * @param bool            $ignore_rejected Whether to ignore rejected or cancelled activities.
     * @param string|NULL     $startDate       The start date for filtering activities.
     * @param string|NULL     $endDate         The end date for filtering activities.
     * @param bool            $load_all_pages  Whether to load all pages of activities.
     *
     * @return array A list of activity objects.
     * @throws Exceptions\UnexpectedException
     * @throws WSApiException If the response format is unexpected.
     */
    public function getActivities($account_id, int $how_many = 50, string $order_by = 'OCCURRED_AT_DESC', bool $ignore_rejected = TRUE, string $startDate = NULL, string $endDate = NULL, bool $load_all_pages = TRUE): array {
        $filter_fn = function ($act) use ($ignore_rejected) {
            if ($act->type === 'LEGACY_TRANSFER') {
                // Never return those
                return FALSE;
            }
            if (empty($act->status)) {
                // No status... What can we do!
                return TRUE;
            }
            if (!$ignore_rejected) {
                // User wants all transactions, including rejected
                return TRUE;
            }
            $is_rejected = array_any(['rejected', 'cancelled', 'expired'], fn($s) => string_contains($act->status, $s));
            if ($is_rejected) {
                return FALSE;
            }
            return TRUE;
        };
        $activities = $this->doGraphQLQuery(
            'FetchActivityFeedItems',
            [
                'orderBy' => $order_by,
                'first'   => $how_many,
                'condition' => [
                    'startDate'  => $startDate ? date('Y-m-d\TH:i:s.999\Z', strtotime($startDate)) : NULL,
                    'endDate'    => date('Y-m-d\TH:i:s.999\Z', $endDate ? strtotime($endDate) : strtotime('+1 day')),
                    'accountIds' => $account_id,
                ],
            ],
            'activityFeedItems.edges',
            'array',
            $filter_fn,
            $load_all_pages
        );
        array_walk($activities, fn($act) => $this->_activityAddDescription($act));
        return $activities;
    }

    private function _activityAddDescription(&$act) {
        $act->description = "$act->type: $act->subType";
        if ($act->type === 'INTERNAL_TRANSFER' || $act->type === 'ASSET_MOVEMENT') {
            $accounts = $this->getAccounts(FALSE);
            $matching = array_filter($accounts, fn($acc) => $acc->id === $act->opposingAccountId);
            $target_account = array_pop($matching);
            if ($target_account) {
                $account_description = "$target_account->description ($target_account->number)";
            } else {
                $account_description = $act->opposingAccountId;
            }
            $direction = $act->subType === 'SOURCE' ? 'to' : 'from';
            $act->description = "Money transfer: $direction Wealthsimple $account_description";
        } elseif ($act->type === 'DIY_BUY' || $act->type === 'DIY_SELL' || $act->type === 'MANAGED_BUY' || $act->type === 'MANAGED_SELL') {
            if (string_contains($act->type, 'MANAGED')) {
                $verb = "Managed transaction";
            } else {
                $verb = ucfirst(strtolower(str_replace('_', ' ', $act->subType)));
            }
            $action = $act->type === 'DIY_BUY' || $act->type === 'MANAGED_BUY' ? 'buy' : 'sell';
            $security = $this->securityIdToSymbol($act->securityId);
            if (empty($act->assetQuantity)) {
                $act->description = "$verb: $action TBD";
            } else {
                $act->description = "$verb: $action " . ((float) $act->assetQuantity) . " x $security @ " . ($act->amount / $act->assetQuantity);
            }
        } elseif ($act->type === 'CORPORATE_ACTION' && $act->subType === 'SUBDIVISION') {
            $child_activities = $this->getCorporateActionChildActivities($act->canonicalId);
            $held_activity = current(array_filter($child_activities, fn ($corp_activity) => $corp_activity->entitlementType === 'HOLD'));
            $receive_activity = current(array_filter($child_activities, fn ($corp_activity) => $corp_activity->entitlementType === 'RECEIVE'));
            if ($held_activity && $receive_activity) {
                $held_shares = (float) $held_activity->quantity;
                $received_shares = (float) $receive_activity->quantity;
                $total_shares = $held_shares + $received_shares;
                $act->description = "Subdivision: $held_shares -> $total_shares shares of $act->assetSymbol";
            } else {
                $received_shares = (float) $act->amount;
                $act->description = "Subdivision: Received $received_shares new shares of $act->assetSymbol";
            }
            if (empty($act->currency)) {
                $market_data = $this->getSecurityMarketData($act->securityId);
                if (!empty($market_data->fundamentals->currency)) {
                    $act->currency = $market_data->fundamentals->currency;
                }
            }
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
        } elseif ($act->type === 'CREDIT_CARD' && $act->subType === 'PURCHASE') {
            $merchant = $act->spendMerchant;
            $status = $act->status === 'authorized' ? '(Pending) ' : ''; // Posted purchase transactions have status = settled
            $act->description = "{$status}Credit card purchase: $merchant";
        } elseif ($act->type === 'CREDIT_CARD' && $act->subType === 'HOLD') {
            $merchant = $act->spendMerchant;
            $status = $act->status === 'authorized' ? '(Pending) ' : ''; // Posted return transactions have subType = REFUND and status = settled
            $act->description = "{$status}Credit card refund: $merchant";
        } elseif ($act->type === 'CREDIT_CARD' && $act->subType === 'REFUND') {
            $merchant = $act->spendMerchant;
            $act->description = "Credit card refund: $merchant";
        } elseif (($act->type === 'CREDIT_CARD' && $act->subType === 'PAYMENT') || $act->type === 'CREDIT_CARD_PAYMENT') {
            $act->description = "Credit card payment";
        } elseif ($act->type === 'REIMBURSEMENT' && $act->subType === 'CASHBACK') {
            $program = '';
            if ($act->rewardProgram === 'CREDIT_CARD_VISA_INFINITE_REWARDS') {
                $program = "- Visa Infinite";
            }
            $act->description = trim("Cash back $program");
        } elseif ($act->type === 'INSTITUTIONAL_TRANSFER_INTENT' && $act->subType === 'TRANSFER_OUT') {
            $act->description = "Institutional transfer: transfer to $act->institutionName";
        } elseif ($act->type === 'SPEND' && $act->subType === 'PREPAID') {
            $merchant = $act->spendMerchant;
            $act->description = "Purchase: $merchant";
        } elseif ($act->type === 'INTEREST_CHARGE') {
            if ($act->subType === 'MARGIN_INTEREST') {
                $act->description = "Interest Charge: margin interest";
            } else {
                $act->description = "Interest Charge";
            }
        } elseif ($act->type === 'FEE' && $act->subType === 'MANAGEMENT_FEE') {
            $act->description = "Management fee";
        }
        // @TODO Add other types
    }

    protected function securityIdToSymbol(string $security_id): string {
        $security_symbol = "[$security_id]";
        if ($this->security_market_data_cache_getter) {
            try {
                $market_data = $this->getSecurityMarketData($security_id);
                if (!empty($market_data->stock)) {
                    $stock = $market_data->stock;
                    $security_symbol = "$stock->primaryExchange:$stock->symbol";
                }
            } catch (\Exception $ex) {
                // Some securities cannot be looked up (e.g., delisted or special securities)
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

    /**
     * Get details about a corporation action (eg. a split)
     *
     * @param string $activity_canonical_id Wealthsimple activity ID
     *
     * @return object[]
     * @throws WSApiException
     */
    public function getCorporateActionChildActivities(string $activity_canonical_id): array {
        return $this->doGraphQLQuery(
            'FetchCorporateActionChildActivities',
            [
                'activityCanonicalId' => $activity_canonical_id,
            ],
            'corporateActionChildActivities.nodes',
            'array',
        );
    }

    /**
     * Retrieve transactions from account monthly statement.
     *
     * @param string $account_id The account ID to retrieve transactions for.
     * @param string $period     The statement start date in 'YYYY-MM-DD' format. For example, '2025-10-01' for October 2025 statement.
     *
     * @return object[] A list of transactions.
     * @throws WSApiException
     */
    public function getStatementTransactions(string $account_id, string $period): array {
        $statements = $this->doGraphQLQuery(
            'FetchBrokerageMonthlyStatementTransactions',
            [
                'accountId' => $account_id,
                'period' => $period,
            ],
            'brokerageMonthlyStatements',
            'array',
        );
        if (is_array($statements) && count($statements) > 0 && !empty($statements[0]->data->currentTransactions)) {
            $transactions = $statements[0]->data->currentTransactions;
        }
        if (empty($transactions)) {
            return [];
        }
        if (!is_array($transactions)) {
            throw new WSApiException("Unexpected response format to GraphQL query 'FetchBrokerageMonthlyStatementTransactions'", 0, $statements);
        }
        return $transactions;
    }

    /**
     * Retrieve information on specific positions.
     *
     * @param string[]|NULL $security_ids         List of Wealthsimple security ids. NULL will return all owned securities.
     * @param string        $currency             Currency to return the amounts in (CAD or USD).
     * @param bool          $include_account_data Whether to include account data.
     *
     * @return object[] A list of positions by account.
     * @throws WSApiException
     */
    public function getIdentityPositions(?array $security_ids = NULL, string $currency = 'CAD', $include_account_data = TRUE): array {
        $positions = $this->doGraphQLQuery(
            'FetchIdentityPositions',
            [
                'identityId'         => $this->getTokenInfo()->identity_canonical_id,
                'currency'           => $currency,
                'filter'             => ['securityIds' => $security_ids],
                'includeAccountData' => $include_account_data
            ],
            'identity.financials.current.positions.edges',
            'array',
        );
        if (!is_array($positions)) {
            throw new WSApiException("Unexpected response format to GraphQL query 'FetchIdentityPositions'", 0, $positions);
        }
        return $positions;
    }

    public function getCreditcardAccount(string $credit_card_account_id): object {
        $account = $this->doGraphQLQuery(
            'FetchCreditCardAccount',
            [
                'id' => $credit_card_account_id,
            ],
            'creditCardAccount',
            'object',
        );
        return $account;
    }
}
