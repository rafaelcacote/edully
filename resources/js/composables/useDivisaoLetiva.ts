import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type DivisaoLetiva = 'bimestral' | 'trimestral';

export interface PeriodoLetivoOption {
    value: string;
    label: string;
}

export interface PeriodoLetivo {
    divisao: DivisaoLetiva;
    quantidade: number;
    unidade: string;
    unidadeTitulo: string;
    opcoes: PeriodoLetivoOption[];
    rotulo: (periodo: number | string | null | undefined) => string;
}

export function periodosDaDivisao(
    divisao: DivisaoLetiva | null | undefined,
): PeriodoLetivo {
    const trimestral = divisao === 'trimestral';
    const quantidade = trimestral ? 3 : 4;
    const unidade = trimestral ? 'trimestre' : 'bimestre';
    const unidadeTitulo = trimestral ? 'Trimestre' : 'Bimestre';

    return {
        divisao: trimestral ? 'trimestral' : 'bimestral',
        quantidade,
        unidade,
        unidadeTitulo,
        opcoes: Array.from({ length: quantidade }, (_, index) => {
            const value = String(index + 1);

            return {
                value,
                label: `${value}º ${unidadeTitulo}`,
            };
        }),
        rotulo(periodo: number | string | null | undefined): string {
            if (periodo === null || periodo === undefined || periodo === '') {
                return '';
            }

            return `${periodo}º ${unidadeTitulo}`;
        },
    };
}

export function useDivisaoLetiva() {
    const page = usePage<AppPageProps>();

    return computed(() => {
        const tenant = page.props.auth?.current_tenant;

        return periodosDaDivisao(tenant?.divisao_letiva);
    });
}
