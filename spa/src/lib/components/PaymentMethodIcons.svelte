<span class="pay-chips" aria-label="Visa, Mastercard, American Express, Discover, Bitcoin">
	<span class="pay-chip pay-chip--visa">VISA</span>
	<span class="pay-chip pay-chip--mc" aria-label="Mastercard"></span>
	<span class="pay-chip pay-chip--amex">AMEX</span>
	<span class="pay-chip pay-chip--disc">DISC</span>
	<span class="pay-chip pay-chip--btc" aria-label="Bitcoin">₿</span>
</span>

<style>
	.pay-chips {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		flex-wrap: wrap;
	}

	.pay-chip {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 34px;
		height: 22px;
		padding: 0 6px;
		border-radius: 4px;
		font-size: 9px;
		font-weight: 700;
		letter-spacing: 0.02em;
		line-height: 1;
	}

	.pay-chip--visa {
		background: #1a1f71;
		color: #fff;
	}

	.pay-chip--mc {
		width: 34px;
		padding: 0;
		background: #000;
		position: relative;
	}

	.pay-chip--mc::before,
	.pay-chip--mc::after {
		content: '';
		position: absolute;
		top: 50%;
		width: 14px;
		height: 14px;
		border-radius: 50%;
		transform: translateY(-50%);
	}

	.pay-chip--mc::before {
		left: 8px;
		background: #eb001b;
	}

	.pay-chip--mc::after {
		right: 8px;
		background: #f79e1b;
	}

	.pay-chip--amex {
		background: #2e77bc;
		color: #fff;
	}

	.pay-chip--disc {
		background: #111;
		color: #fff;
	}

	.pay-chip--btc {
		background: #f7931a;
		color: #fff;
		font-size: 12px;
	}
</style>
