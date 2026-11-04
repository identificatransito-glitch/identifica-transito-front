import assert from 'node:assert/strict';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve,dirname} from 'node:path';
import {existsSync} from 'node:fs';
const here=dirname(fileURLToPath(import.meta.url));
const candidate=resolve(here,'../identifica-transito-front-trabalho');
const root=process.argv[2] || (existsSync(candidate+'/app') ? candidate : resolve(here,'../..'));
const {findPickupPoints,pointRegion,regionPriority,regionLabel,pickupMapUrl}=await import(pathToFileURL(resolve(root,'app/utils/pickupPoints.ts')).href);
import {readFileSync} from 'node:fs';
let checks=0; const check=(condition)=>{assert.ok(condition);checks++};
const points=[
{id:1,name:'Ponto Centro',address:'Rua Exemplo — nº 10 — bairro Centro — Mauá/SP — CEP 09361-030'},
{id:2,name:'Ponto Jardim',address:'Rua Exemplo — nº 20 — bairro Jardim — Mauá/SP — CEP 09370-000'},
{id:3,name:'Ponto Litoral',address:'Rua Exemplo — bairro Centro — Santos/SP — CEP 11000-000'},
{id:4,name:'Ponto de outro estado',address:'Rua Exemplo — Rio de Janeiro/RJ — CEP 20000-000'},
{id:5,name:'Sem endereço',address:null},
{id:6,name:'Inativo',active:false,address:'Mauá/SP'},
{id:7,name:'Inativo texto',active:'0',address:'Mauá/SP'},
];
const ref={zip:'09361030',city:'Mauá',state:'SP',neighborhood:'Centro'};
check(JSON.stringify(pointRegion(points[0]))===JSON.stringify(ref));
check(regionPriority(points[0],ref)===0);
check(regionPriority(points[0],{...ref,zip:'09999999'})===1);
check(regionPriority(points[1],ref)===2);
check(regionPriority(points[2],ref)===3);
check(regionPriority(points[3],ref)===4);
check(regionPriority(points[4],ref)===4);
check(findPickupPoints(points,'maua centro',null).map(p=>p.id).join()==='1');
check(findPickupPoints(points,'JARDIM',null).map(p=>p.id).join()==='2');
check(findPickupPoints(points,'inativo',null).length===0);
check(findPickupPoints(points,'',ref).map(p=>p.id).join()==='1,2,3,4,5');
check(findPickupPoints(points,'inexistente',ref).length===0);
check(findPickupPoints(points,'',null).length===5);
check(regionLabel(4)==='');
check(pickupMapUrl({id:1,name:'test',map_link:'javascript:alert(1)',address:'Rua teste'}).startsWith('https://www.google.com/maps/search/'));
check(pickupMapUrl({id:1,name:'test',map_link:'https://maps.google.com/test'})==='https://maps.google.com/test');
check(pickupMapUrl({id:1,name:'test',address:null})==='');
const source=readFileSync(resolve(root,'app/pages/dashboard/pagamento.vue'),'utf8');
check(source.includes('const CREDIT_CARD_ENABLED = false;'));
check(source.includes('v-if="CREDIT_CARD_ENABLED"'));
check(source.includes('v-if="CREDIT_CARD_ENABLED && !isFree && paymentMethod'));
check(source.includes("paymentMethod === 'pix'")&&source.includes("paymentMethod === 'boleto'"));
check(source.includes('if (!CREDIT_CARD_ENABLED)'));
check(source.includes('<PickupPointSelector'));
console.log(`PASS ${checks} verificações: busca, região, inativos, mapa seguro, seleção opcional e cartão oculto.`);
