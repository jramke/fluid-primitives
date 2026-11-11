import { mountAll } from 'fluid-primitives';
import { Pagination } from 'fluid-primitives/pagination';

mountAll('ui:pagination', ({ props }) => new Pagination(props).init());
