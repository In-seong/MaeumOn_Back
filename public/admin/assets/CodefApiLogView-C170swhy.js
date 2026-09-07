import{d as et,h as ot,r,i as j,j as nt,k as st,B as A,c as l,a as e,y as at,e as k,b as y,v as V,A as h,t as s,F as T,s as O,m as U,n as lt,aj as pt,T as it,q as C,x as Y,o as a,al as rt}from"./admin-B98pS51h.js";import{_ as dt}from"./Pagination.vue_vue_type_script_setup_true_lang-Ckp99Xmt.js";const xt={class:"p-4 lg:p-6"},ct={class:"mb-4 flex flex-wrap items-center gap-3"},ut={class:"relative"},gt={key:0,class:"text-center py-10"},mt={key:1,class:"bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.06)] overflow-x-auto"},ft={class:"min-w-full divide-y divide-[#E8E8E8]"},yt={class:"divide-y divide-[#F0F0F0]"},bt=["onClick"],ht={class:"px-4 lg:px-6 py-4 text-[14px] text-[#999]"},vt={class:"px-4 lg:px-6 py-4 text-[14px] font-medium text-[#333]"},_t={class:"px-4 lg:px-6 py-4 text-[14px] text-center"},Ft={key:0,class:"px-2 py-0.5 bg-blue-50 text-blue-600 text-[12px] font-medium rounded-full"},wt={key:1,class:"text-[#ccc]"},kt={class:"px-4 lg:px-6 py-4 text-[14px] text-center"},$t={key:0,class:"px-2 py-0.5 bg-purple-50 text-purple-600 text-[12px] font-medium rounded-full"},Bt={key:1,class:"text-[#ccc]"},St={class:"px-4 lg:px-6 py-4 text-[14px] text-center"},zt={key:0,class:"px-2 py-0.5 bg-green-50 text-green-600 text-[12px] font-medium rounded-full"},At={key:1,class:"text-[#ccc]"},Ct={class:"px-4 lg:px-6 py-4 text-[14px] text-center"},Lt={key:0,class:"px-2 py-0.5 bg-orange-50 text-orange-600 text-[12px] font-medium rounded-full"},Et={key:1,class:"text-[#ccc]"},Pt={class:"px-4 lg:px-6 py-4 text-[14px] text-center text-green-600 font-medium"},Nt={class:"px-4 lg:px-6 py-4 text-[14px] text-center"},Dt={class:"px-4 lg:px-6 py-4 text-[15px] text-center font-bold text-[#333]"},It={key:0},Mt={key:0,class:"px-6 py-3 border-t border-[#F0F0F0] text-right text-[14px] text-[#999]"},jt={class:"font-bold text-[#333]"},Vt={class:"font-bold text-[#FF7B22]"},Tt={key:0,class:"fixed inset-0 z-50 flex items-center justify-center p-4"},Ot={class:"relative bg-white rounded-[20px] shadow-2xl w-full max-w-[1100px] max-h-[85vh] flex flex-col"},Ut={class:"flex items-center justify-between px-6 py-5 border-b border-[#F0F0F0]"},Yt={class:"text-[18px] font-bold text-[#333]"},Ht={class:"text-[13px] text-[#999] mt-0.5"},Wt={class:"font-semibold text-[#FF7B22]"},Gt={class:"px-6 py-3 border-b border-[#F5F5F5] flex flex-wrap items-center gap-3"},qt={class:"ml-auto"},Rt={class:"flex-1 overflow-auto"},Jt={key:0,class:"text-center py-10"},Kt={key:1,class:"min-w-full divide-y divide-[#E8E8E8]"},Qt={class:"divide-y divide-[#F0F0F0]"},Xt={class:"px-5 py-3.5 text-[13px] text-[#999]"},Zt={class:"px-5 py-3.5 text-[13px] text-[#555]"},te={class:"px-5 py-3.5"},ee={class:"px-5 py-3.5 text-[13px] text-[#555]"},oe={class:"px-5 py-3.5"},ne={class:"px-5 py-3.5 text-[13px] text-[#555]"},se={class:"px-5 py-3.5 text-[12px] text-[#999] max-w-[180px] truncate"},ae={class:"px-5 py-3.5 text-[12px] text-[#999] whitespace-nowrap"},le={key:0},pe={key:0,class:"border-t border-[#F0F0F0]"},ie=et({__name:"CodefApiLogView",setup(re){const L=ot(),$=r(!1),B=r(!1),v=r([]),E=r(0),_=r(""),b=r(600),g=j(()=>{if(!_.value.trim())return v.value;const n=_.value.trim().toLowerCase();return v.value.filter(t=>t.agent_name.toLowerCase().includes(n))}),P=j(()=>g.value.reduce((n,t)=>n+t.total_count,0)),u=r(null),F=r([]),d=r(null),N=new Date,x=r(`${N.getFullYear()}-${String(N.getMonth()+1).padStart(2,"0")}`),c=r({api_type:"",status:""});function H(n){return{insurance:"보험",medical:"진료",checkup:"검진",health_age:"건강나이"}[n]||n}function W(n){return{insurance:"bg-blue-50 text-blue-600",medical:"bg-purple-50 text-purple-600",checkup:"bg-green-50 text-green-600",health_age:"bg-orange-50 text-orange-600"}[n]||"bg-gray-100 text-[#999]"}function G(n){return n==="fetch"?"조회":n==="confirm"?"인증완료":n}function q(n){return{success:"성공",failed:"실패",two_way:"2-Way"}[n]||n}function R(n){return{success:"bg-green-50 text-green-600",failed:"bg-red-50 text-red-600",two_way:"bg-yellow-50 text-yellow-700"}[n]||"bg-gray-100 text-[#999]"}function J(n){if(!n)return"-";const t=new Date(n),o=p=>String(p).padStart(2,"0");return`${t.getFullYear()}.${o(t.getMonth()+1)}.${o(t.getDate())} ${o(t.getHours())}:${o(t.getMinutes())}`}function K(n){return d.value?(d.value.current_page-1)*d.value.per_page+n+1:n+1}async function S(){$.value=!0;try{const n={...L.getBranchParam()};x.value&&(n.month=x.value);const t=await A.get("/admin/codef-billing/summary",{params:n});v.value=t.data.data.agents,E.value=t.data.data.total}catch{v.value=[],E.value=0}finally{$.value=!1}}function Q(n){u.value=n,c.value={api_type:"",status:""},w()}async function w(n=1){if(u.value){B.value=!0;try{const t={agent_id:u.value.agent_id,page:n,per_page:30};x.value&&(t.month=x.value),c.value.api_type&&(t.api_type=c.value.api_type),c.value.status&&(t.status=c.value.status);const p=(await A.get("/admin/codef-billing/logs",{params:t})).data.data;F.value=p.data,d.value={current_page:p.current_page,last_page:p.last_page,per_page:p.per_page,total:p.total}}catch{F.value=[]}finally{B.value=!1}}}function z(n){const t=n.split("-"),o=t[0]??"",p=t[1]??"";return`${o}년 ${parseInt(p)||0}월`}function D(){const n=new Date,t=o=>String(o).padStart(2,"0");return`${n.getFullYear()}년 ${t(n.getMonth()+1)}월 ${t(n.getDate())}일`}function I(n){const t=[{label:"보험 조회",count:n.insurance_count},{label:"진료 조회",count:n.medical_count},{label:"검진 조회",count:n.checkup_count},{label:"건강나이 조회",count:n.health_age_count}],o=b.value,p=t.map(i=>`
    <tr>
      <td style="padding:10px 16px;border-bottom:1px solid #eee;">${i.label}</td>
      <td style="padding:10px 16px;border-bottom:1px solid #eee;text-align:right;">${i.count.toLocaleString()}건</td>
      <td style="padding:10px 16px;border-bottom:1px solid #eee;text-align:right;">${o.toLocaleString()}원</td>
      <td style="padding:10px 16px;border-bottom:1px solid #eee;text-align:right;font-weight:600;">${(i.count*o).toLocaleString()}원</td>
    </tr>
  `).join(""),m=n.total_count*o;return`
    <div style="max-width:680px;margin:0 auto;padding:48px 40px;font-family:'Pretendard','Apple SD Gothic Neo',sans-serif;color:#222;">
      <div style="text-align:center;margin-bottom:36px;">
        <h1 style="font-size:26px;font-weight:800;margin:0 0 6px;">API 사용료 청구서</h1>
        <p style="font-size:13px;color:#999;margin:0;">Invoice</p>
      </div>

      <div style="display:flex;justify-content:space-between;margin-bottom:28px;font-size:14px;">
        <div>
          <p style="margin:0 0 4px;color:#999;font-size:12px;">청구 대상</p>
          <p style="margin:0;font-size:18px;font-weight:700;">${n.agent_name}</p>
        </div>
        <div style="text-align:right;">
          <p style="margin:0 0 4px;color:#999;font-size:12px;">서비스 기간</p>
          <p style="margin:0;font-weight:600;">${z(x.value)}</p>
          <p style="margin:4px 0 0;color:#999;font-size:12px;">발행일: ${D()}</p>
        </div>
      </div>

      <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:24px;">
        <thead>
          <tr style="background:#f8f8f8;">
            <th style="padding:10px 16px;text-align:left;font-weight:600;border-bottom:2px solid #ddd;">항목</th>
            <th style="padding:10px 16px;text-align:right;font-weight:600;border-bottom:2px solid #ddd;">건수</th>
            <th style="padding:10px 16px;text-align:right;font-weight:600;border-bottom:2px solid #ddd;">단가</th>
            <th style="padding:10px 16px;text-align:right;font-weight:600;border-bottom:2px solid #ddd;">금액</th>
          </tr>
        </thead>
        <tbody>
          ${p}
        </tbody>
        <tfoot>
          <tr style="background:#FFF8F3;">
            <td style="padding:12px 16px;font-weight:700;border-top:2px solid #FF7B22;">합계</td>
            <td style="padding:12px 16px;text-align:right;font-weight:700;border-top:2px solid #FF7B22;">${n.total_count.toLocaleString()}건</td>
            <td style="padding:12px 16px;border-top:2px solid #FF7B22;"></td>
            <td style="padding:12px 16px;text-align:right;font-weight:800;font-size:16px;color:#FF7B22;border-top:2px solid #FF7B22;">${m.toLocaleString()}원</td>
          </tr>
        </tfoot>
      </table>

      <div style="background:#f8f8f8;border-radius:8px;padding:16px 20px;font-size:13px;color:#666;margin-bottom:32px;">
        <p style="margin:0 0 4px;font-weight:600;color:#333;">비고</p>
        <p style="margin:0;">API 사용 건수 기준 과금 (건당 ${o.toLocaleString()}원)</p>
      </div>

      <div style="text-align:center;border-top:1px solid #eee;padding-top:24px;font-size:13px;color:#999;">
        <p style="margin:0;font-weight:600;color:#555;">보험ON (MaeumON)</p>
        <p style="margin:4px 0 0;">본 청구서는 전산 발행되었습니다.</p>
      </div>
    </div>
  `}function M(n){const t=window.open("","_blank");t&&(t.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>청구서</title>
    <style>
      @page { size: A4; margin: 20mm; }
      body { margin:0; }
      .page-break { page-break-after: always; }
      .page-break:last-child { page-break-after: auto; }
      @media print {
        .no-print { display: none !important; }
      }
    </style>
  </head><body>
    <div class="no-print" style="text-align:center;padding:16px;background:#f5f5f5;font-family:sans-serif;">
      <button onclick="window.print()" style="padding:10px 28px;background:#FF7B22;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-right:8px;">인쇄 / PDF 저장</button>
      <button onclick="window.close()" style="padding:10px 28px;background:#666;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">닫기</button>
    </div>
    ${n}
  </body></html>`),t.document.close())}function X(n){M(I(n))}function Z(n){const t=b.value,o=n.reduce((i,f)=>i+f.total_count,0),p=o*t,m=n.map((i,f)=>`
    <tr>
      <td style="padding:8px 16px;border-bottom:1px solid #eee;text-align:center;">${f+1}</td>
      <td style="padding:8px 16px;border-bottom:1px solid #eee;">${i.agent_name}</td>
      <td style="padding:8px 16px;border-bottom:1px solid #eee;text-align:right;">${i.total_count.toLocaleString()}건</td>
      <td style="padding:8px 16px;border-bottom:1px solid #eee;text-align:right;">${(i.total_count*t).toLocaleString()}원</td>
    </tr>
  `).join("");return`
    <div style="max-width:680px;margin:0 auto;padding:48px 40px;font-family:'Pretendard','Apple SD Gothic Neo',sans-serif;color:#222;">
      <div style="text-align:center;margin-bottom:36px;">
        <h1 style="font-size:26px;font-weight:800;margin:0 0 6px;">API 사용료 청구 총괄표</h1>
        <p style="font-size:13px;color:#999;margin:0;">Monthly Summary</p>
      </div>

      <div style="background:#FFF8F3;border:2px solid #FF7B22;border-radius:12px;padding:24px 28px;text-align:center;margin-bottom:28px;">
        <p style="margin:0 0 6px;font-size:13px;color:#999;">${z(x.value)} 총 합계 비용</p>
        <p style="margin:0;font-size:32px;font-weight:800;color:#FF7B22;">${p.toLocaleString()}원</p>
        <p style="margin:8px 0 0;font-size:14px;color:#666;">총 ${o.toLocaleString()}건 · ${n.length}명 · 건당 ${t.toLocaleString()}원</p>
      </div>

      <div style="display:flex;justify-content:space-between;margin-bottom:20px;font-size:14px;">
        <div>
          <p style="margin:0 0 4px;color:#999;font-size:12px;">서비스 기간</p>
          <p style="margin:0;font-weight:600;">${z(x.value)}</p>
        </div>
        <div style="text-align:right;">
          <p style="margin:0 0 4px;color:#999;font-size:12px;">발행일</p>
          <p style="margin:0;font-weight:600;">${D()}</p>
        </div>
      </div>

      <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:24px;">
        <thead>
          <tr style="background:#f8f8f8;">
            <th style="padding:10px 16px;text-align:center;font-weight:600;border-bottom:2px solid #ddd;width:50px;">No.</th>
            <th style="padding:10px 16px;text-align:left;font-weight:600;border-bottom:2px solid #ddd;">설계사</th>
            <th style="padding:10px 16px;text-align:right;font-weight:600;border-bottom:2px solid #ddd;">건수</th>
            <th style="padding:10px 16px;text-align:right;font-weight:600;border-bottom:2px solid #ddd;">금액</th>
          </tr>
        </thead>
        <tbody>${m}</tbody>
        <tfoot>
          <tr style="background:#FFF8F3;">
            <td colspan="2" style="padding:12px 16px;font-weight:700;border-top:2px solid #FF7B22;">합계</td>
            <td style="padding:12px 16px;text-align:right;font-weight:700;border-top:2px solid #FF7B22;">${o.toLocaleString()}건</td>
            <td style="padding:12px 16px;text-align:right;font-weight:800;font-size:16px;color:#FF7B22;border-top:2px solid #FF7B22;">${p.toLocaleString()}원</td>
          </tr>
        </tfoot>
      </table>

      <div style="text-align:center;border-top:1px solid #eee;padding-top:24px;font-size:13px;color:#999;">
        <p style="margin:0;font-weight:600;color:#555;">보험ON (MaeumON)</p>
        <p style="margin:4px 0 0;">본 청구서는 전산 발행되었습니다.</p>
      </div>
    </div>
  `}function tt(){const n=g.value.filter(p=>p.total_count>0);if(n.length===0)return;const t=`<div class="page-break">${Z(n)}</div>`,o=n.map((p,m,i)=>{const f=I(p);return m<i.length-1?`<div class="page-break">${f}</div>`:`<div>${f}</div>`}).join("");M(t+o)}return nt(()=>L.selectedBranchId,()=>{S()}),st(async()=>{try{const t=(await A.get("/admin/settings")).data.data;t.codef_unit_price&&(b.value=Number(t.codef_unit_price.value)||600)}catch{}S()}),(n,t)=>(a(),l("div",xt,[t[23]||(t[23]=e("h1",{class:"text-[22px] font-bold text-[#333] mb-6"},"CODEF API 사용 로그",-1)),e("div",ct,[k(e("input",{"onUpdate:modelValue":t[0]||(t[0]=o=>x.value=o),type:"month",class:"px-4 py-2.5 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] focus:outline-none focus:border-[#FF7B22] text-[14px] text-[#333]",onChange:S},null,544),[[V,x.value]]),e("div",ut,[t[9]||(t[9]=e("span",{class:"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-[#BBB]"},"search",-1)),k(e("input",{"onUpdate:modelValue":t[1]||(t[1]=o=>_.value=o),type:"text",placeholder:"설계사 이름 검색",class:"pl-9 pr-4 py-2.5 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] focus:outline-none focus:border-[#FF7B22] text-[14px] text-[#333] w-[200px]"},null,512),[[V,_.value]])]),g.value.length>0?(a(),l("button",{key:0,onClick:tt,class:"ml-auto flex items-center gap-1.5 px-4 py-2.5 bg-[#333] text-white text-[13px] font-medium rounded-[12px] hover:bg-[#555] transition-colors"},[t[10]||(t[10]=e("span",{class:"material-symbols-outlined text-[18px]"},"print",-1)),h(" 월별 청구서 일괄 발행 ("+s(g.value.length)+"명) ",1)])):y("",!0)]),$.value?(a(),l("div",gt,[...t[11]||(t[11]=[e("div",{class:"animate-spin rounded-full h-10 w-10 border-b-2 border-[#FF7B22] mx-auto"},null,-1)])])):(a(),l("div",mt,[e("table",ft,[t[13]||(t[13]=e("thead",{class:"bg-[#FAFAFA]"},[e("tr",null,[e("th",{class:"px-4 lg:px-6 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"No."),e("th",{class:"px-4 lg:px-6 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"설계사"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase"},"보험"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase"},"진료"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase"},"검진"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase"},"건강나이"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase"},"성공"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase"},"실패"),e("th",{class:"px-4 lg:px-6 py-3 text-center text-[12px] font-medium text-[#999] uppercase font-bold"},"총 건수")])],-1)),e("tbody",yt,[(a(!0),l(T,null,O(g.value,(o,p)=>(a(),l("tr",{key:o.agent_id,class:"hover:bg-[#FFF8F3] transition-colors cursor-pointer",onClick:m=>Q(o)},[e("td",ht,s(p+1),1),e("td",vt,s(o.agent_name),1),e("td",_t,[o.insurance_count>0?(a(),l("span",Ft,s(o.insurance_count),1)):(a(),l("span",wt,"-"))]),e("td",kt,[o.medical_count>0?(a(),l("span",$t,s(o.medical_count),1)):(a(),l("span",Bt,"-"))]),e("td",St,[o.checkup_count>0?(a(),l("span",zt,s(o.checkup_count),1)):(a(),l("span",At,"-"))]),e("td",Ct,[o.health_age_count>0?(a(),l("span",Lt,s(o.health_age_count),1)):(a(),l("span",Et,"-"))]),e("td",Pt,s(o.success_count),1),e("td",Nt,[e("span",{class:C(o.failed_count>0?"text-red-500 font-medium":"text-[#ccc]")},s(o.failed_count||"-"),3)]),e("td",Dt,s(o.total_count)+"건",1)],8,bt))),128)),g.value.length===0?(a(),l("tr",It,[...t[12]||(t[12]=[e("td",{colspan:"9",class:"px-4 lg:px-6 py-10 text-center text-[#999]"},"해당 월에 API 사용 기록이 없습니다.",-1)])])):y("",!0)])]),g.value.length>0?(a(),l("div",Mt,[t[14]||(t[14]=h(" 전체 합계: ",-1)),e("span",jt,s(P.value)+"건",1),t[15]||(t[15]=h(" · 총 사용료: ",-1)),e("span",Vt,s((P.value*b.value).toLocaleString())+"원",1)])):y("",!0)])),(a(),at(it,{to:"body"},[U(pt,{name:"modal"},{default:lt(()=>[u.value?(a(),l("div",Tt,[e("div",{class:"absolute inset-0 bg-black/40",onClick:t[2]||(t[2]=o=>u.value=null)}),e("div",Ot,[e("div",Ut,[e("div",null,[e("h2",Yt,s(u.value.agent_name)+" — API 사용 상세",1),e("p",Ht,[h(s(x.value||"전체 기간")+" · 총 "+s(d.value?.total??0)+"건 · 사용료 ",1),e("span",Wt,s(((d.value?.total??0)*b.value).toLocaleString())+"원",1)])]),e("button",{onClick:t[3]||(t[3]=o=>u.value=null),class:"w-9 h-9 flex items-center justify-center rounded-full hover:bg-[#F0F0F0] transition-colors"},[...t[16]||(t[16]=[e("span",{class:"material-symbols-outlined text-[22px] text-[#999]"},"close",-1)])])]),e("div",Gt,[k(e("select",{"onUpdate:modelValue":t[4]||(t[4]=o=>c.value.api_type=o),class:"px-3 py-2 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] focus:outline-none focus:border-[#FF7B22] text-[13px] text-[#333]",onChange:t[5]||(t[5]=o=>w())},[...t[17]||(t[17]=[e("option",{value:""},"전체 API",-1),e("option",{value:"insurance"},"보험",-1),e("option",{value:"medical"},"진료",-1),e("option",{value:"checkup"},"검진",-1),e("option",{value:"health_age"},"건강나이",-1)])],544),[[Y,c.value.api_type]]),k(e("select",{"onUpdate:modelValue":t[6]||(t[6]=o=>c.value.status=o),class:"px-3 py-2 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] focus:outline-none focus:border-[#FF7B22] text-[13px] text-[#333]",onChange:t[7]||(t[7]=o=>w())},[...t[18]||(t[18]=[e("option",{value:""},"전체 상태",-1),e("option",{value:"success"},"성공",-1),e("option",{value:"failed"},"실패",-1),e("option",{value:"two_way"},"2-Way 대기",-1)])],544),[[Y,c.value.status]]),e("div",qt,[e("button",{onClick:t[8]||(t[8]=o=>X(u.value)),class:"flex items-center gap-1.5 px-4 py-2 bg-[#FF7B22] text-white text-[13px] font-medium rounded-[10px] hover:bg-[#E66A1A] transition-colors"},[...t[19]||(t[19]=[e("span",{class:"material-symbols-outlined text-[18px]"},"receipt_long",-1),h(" 청구서 발행 ",-1)])])])]),e("div",Rt,[B.value?(a(),l("div",Jt,[...t[20]||(t[20]=[e("div",{class:"animate-spin rounded-full h-8 w-8 border-b-2 border-[#FF7B22] mx-auto"},null,-1)])])):(a(),l("table",Kt,[t[22]||(t[22]=e("thead",{class:"bg-[#FAFAFA] sticky top-0"},[e("tr",null,[e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"No."),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"고객"),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"API 종류"),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"액션"),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"상태"),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"결과"),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"에러"),e("th",{class:"px-5 py-3 text-left text-[12px] font-medium text-[#999] uppercase"},"일시")])],-1)),e("tbody",Qt,[(a(!0),l(T,null,O(F.value,(o,p)=>(a(),l("tr",{key:o.log_id,class:"hover:bg-[#FAFAFA] transition-colors"},[e("td",Xt,s(K(p)),1),e("td",Zt,s(o.customer?.name||"-"),1),e("td",te,[e("span",{class:C([W(o.api_type),"px-2 py-0.5 text-[11px] font-medium rounded-full"])},s(H(o.api_type)),3)]),e("td",ee,s(G(o.api_action)),1),e("td",oe,[e("span",{class:C([R(o.status),"px-2 py-0.5 text-[11px] font-medium rounded-full"])},s(q(o.status)),3)]),e("td",ne,s(o.result_count??0)+"건",1),e("td",se,s(o.error_message||"-"),1),e("td",ae,s(J(o.created_at)),1)]))),128)),F.value.length===0?(a(),l("tr",le,[...t[21]||(t[21]=[e("td",{colspan:"8",class:"px-5 py-10 text-center text-[#999]"},"로그가 없습니다.",-1)])])):y("",!0)])]))]),d.value&&d.value.last_page>1?(a(),l("div",pe,[U(dt,{"current-page":d.value.current_page,"last-page":d.value.last_page,onChange:w},null,8,["current-page","last-page"])])):y("",!0)])])):y("",!0)]),_:1})]))]))}}),ce=rt(ie,[["__scopeId","data-v-36b55994"]]);export{ce as default};
