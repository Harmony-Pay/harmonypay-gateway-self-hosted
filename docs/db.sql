CREATE SCHEMA api;

DROP TABLE api.orders;
CREATE TABLE api.orders (
id SERIAL PRIMARY KEY,
payment_id INT,
transaction_id VARCHAR(160),
amount NUMERIC(5,2),
confirmations SMALLINT,
currency_id VARCHAR(9),
autosettlements JSON[],
timeout_hours INT,
microtime VARCHAR(6),
to_address VARCHAR(160),
domain VARCHAR(160),
domain_key VARCHAR(30),
status SMALLINT,
created_at TIMESTAMP default current_timestamp);
CREATE INDEX idx_id ON api.orders(id);
CREATE INDEX idx_payment_id ON api.orders(payment_id);
CREATE INDEX idx_to ON api.orders(to_address);


DROP TABLE api.transactions;
CREATE TABLE api.transactions (
id SERIAL PRIMARY KEY,
order_id INT,
block_hash VARCHAR(255),
block_number BIGINT,
from_address VARCHAR(160),
gas INT, 
gas_price VARCHAR(30),
hash VARCHAR(255),
input TEXT,
nonce INT,
r VARCHAR(255),
s VARCHAR(255),
timestamp VARCHAR(30),
to_address VARCHAR(160),
transaction_index SMALLINT,
v VARCHAR(160),
value TEXT,
created_at TIMESTAMP default current_timestamp);
CREATE INDEX idx_trans_id ON api.transactions(id);
CREATE INDEX idx_order_id ON api.transactions(order_id);
CREATE INDEX idx_trans_to ON api.transactions(to_address);


DROP TABLE api.donations;
CREATE TABLE api.donations (
id SERIAL PRIMARY KEY,
transaction_id VARCHAR(160),
amount VARCHAR(30),
confirmations SMALLINT,
currency_id VARCHAR(9),
autosettlements JSON[],
microtime VARCHAR(6),
from_address VARCHAR(160),
to_address VARCHAR(160),
domain VARCHAR(160),
domain_key VARCHAR(30),
status SMALLINT,
created_at TIMESTAMP default current_timestamp);
CREATE INDEX idx_donations_id ON api.donations(id);
CREATE INDEX idx_donations_to ON api.donations(to_address);

DROP TABLE api.wallets;
CREATE TABLE api.wallets (
id SERIAL PRIMARY KEY,
currency_id VARCHAR(30),
address VARCHAR(160),
confirmations SMALLINT,
enabled BOOLEAN,
created_at TIMESTAMP default current_timestamp);
CREATE INDEX idx_wallets_id ON api.wallets(id);
CREATE INDEX idx_wallets_to ON api.wallets(address);


DROP TABLE api.settlements;
CREATE TABLE api.settlements (
id SERIAL PRIMARY KEY,
order_id INT,
settlement_type VARCHAR(30),
settlement_info JSON,
settlement_pair VARCHAR(15),
settlement_amount NUMERIC(5,2),
transaction_id VARCHAR(160),
amount NUMERIC(5,2),
currency_id VARCHAR(9),
to_address VARCHAR(160),
order_info JSON,
status SMALLINT,
created_at TIMESTAMP default current_timestamp);
CREATE INDEX idx_settlements_id ON api.settlements(id);
CREATE INDEX idx_settlements_to ON api.settlements(to_address);


DROP TABLE api.coins;
CREATE TABLE api.coins (
id SERIAL PRIMARY KEY,
symbol VARCHAR(15),
name VARCHAR(60),
address_length INT,
decimal_precision INT,
token_group VARCHAR(60),
token_image VARCHAR(160),
contract VARCHAR(160),
erc20 BOOLEAN,
hrc20 BOOLEAN,
metamask_abi JSON,
metamask_currency VARCHAR(15),
metamask_gas VARCHAR(30),
metamask_gas_limit VARCHAR(30),
wp_plugin_open_in_wallet BOOLEAN,
active BOOLEAN,
created_at TIMESTAMP default current_timestamp);
CREATE INDEX idx_coin_id ON api.coins(id);
CREATE INDEX idx_coin_name ON api.coins(name);