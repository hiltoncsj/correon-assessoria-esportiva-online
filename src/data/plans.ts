/*
 * Configuração comercial da Hero 6 — ponto único para preços.
 *
 * Regras:
 *  - `null` = preço ainda não definido pela administração. A interface mostra
 *    "Em breve" e NÃO permite contratar esse período.
 *  - Só o preço mensal para NOVOS alunos está definido (R$ 150,00 por modalidade).
 *  - Trimestral, semestral e anual: não inventar valores nem descontos.
 *  - O preço legado de alunos antigos NÃO fica neste arquivo (é público no
 *    navegador). Ele pertence ao backend/cadastro, que ainda não existe.
 *  - Estes valores são só de exibição. A validação final do preço
 *    deve ser feita no servidor.
 */

export type ModalityId = 'corrida' | 'triathlon' | 'musculacao';
export type PeriodId = 'mensal' | 'trimestral' | 'semestral' | 'anual';

export const periods: { id: PeriodId; label: string }[] = [
	{ id: 'mensal', label: 'Mensal' },
	{ id: 'trimestral', label: 'Trimestral' },
	{ id: 'semestral', label: 'Semestral' },
	{ id: 'anual', label: 'Anual' },
];

export const modalities: { id: ModalityId; name: string; description: string }[] = [
	{
		id: 'corrida',
		name: 'Corrida',
		description:
			'Treinamento personalizado para quem deseja começar a correr, ganhar consistência ou superar seus limites.',
	},
	{
		id: 'triathlon',
		name: 'Triathlon',
		description: 'Planejamento integrado para evolução na natação, ciclismo e corrida.',
	},
	{
		id: 'musculacao',
		name: 'Musculação',
		description: 'Treinamento estruturado para desenvolver força, condicionamento e desempenho.',
	},
];

/** Preço em reais por modalidade e período (novos alunos). */
export const prices: Record<ModalityId, Record<PeriodId, number | null>> = {
	corrida: { mensal: 150, trimestral: null, semestral: null, anual: null },
	triathlon: { mensal: 150, trimestral: null, semestral: null, anual: null },
	musculacao: { mensal: 150, trimestral: null, semestral: null, anual: null },
};

/**
 * Grupos excludentes: só uma modalidade de cada grupo pode estar selecionada.
 * Corrida e Triathlon não se combinam (é uma ou outra).
 */
export const exclusiveGroups: ModalityId[][] = [['corrida', 'triathlon']];

/**
 * Combinações com preço próprio (desconto de combo). Só vale quando TODAS as
 * modalidades da combinação estão no mesmo período e há preço definido nele;
 * caso contrário, soma os preços individuais. Combinações que não existem
 * (Corrida + Triathlon, as três juntas) são impedidas por `exclusiveGroups`.
 * Musculação sozinha continua com o preço individual (R$ 150/mês).
 */
export const bundles: { modalities: ModalityId[]; prices: Record<PeriodId, number | null> }[] = [
	{
		modalities: ['corrida', 'musculacao'],
		prices: { mensal: 200, trimestral: null, semestral: null, anual: null },
	},
	{
		modalities: ['triathlon', 'musculacao'],
		prices: { mensal: 200, trimestral: null, semestral: null, anual: null },
	},
];

/** Benefícios incluídos nos três produtos digitais (não acrescentar outros). */
export const benefits = [
	'Planilha de treinamento personalizada',
	'Acesso à plataforma CorreON',
	'Acompanhamento do treinador',
];

/** Formatos comerciais do Personal Trainer (valores ainda não cadastrados). */
export const personalFormats = [
	{ id: 'pacotes', label: 'Pacotes de sessões' },
	{ id: 'mensalidade', label: 'Mensalidade com frequência semanal definida' },
	{ id: 'avulsa', label: 'Aula avulsa' },
];
