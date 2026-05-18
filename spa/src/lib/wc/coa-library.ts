export type CoaLibraryCertificate = {
	id: number;
	variation_label: string;
	coa_url: string;
	batch: string;
	lab: string;
	tested: string;
};

export type CoaLibraryProduct = {
	id: number;
	name: string;
	slug: string;
	certificates: CoaLibraryCertificate[];
};

export type CoaLibraryResponse = {
	products: CoaLibraryProduct[];
};

export async function fetchCoaLibrary(): Promise<CoaLibraryProduct[]> {
	const res = await fetch('/wp-json/wchs/v1/coa-library');
	if (!res.ok) throw new Error(`COA library failed (${res.status})`);
	const data = (await res.json()) as CoaLibraryResponse;
	return data.products ?? [];
}

export function formatCoaTestedDate(iso: string): string {
	if (!iso) return '';
	const d = new Date(iso);
	if (Number.isNaN(d.getTime())) return '';
	return new Intl.DateTimeFormat(undefined, {
		day: 'numeric',
		month: 'short',
		year: 'numeric',
	}).format(d);
}
