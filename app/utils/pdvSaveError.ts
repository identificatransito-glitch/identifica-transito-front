const fields = ['access_email','password','password_confirmation','responsible','name','cnpj','address','phone','map_link','commission_percentage'];
export const pdvSaveError = (error: any): string => {
  const status = Number(error?.statusCode || error?.status || error?.response?.status || 0);
  if (status === 401) return 'Sua sessão expirou. Entre novamente para salvar o PDV.';
  if (status === 403) return 'Somente o Super Admin pode salvar o PDV.';
  if (status >= 500) return 'O servidor não conseguiu salvar o PDV. Tente novamente em instantes.';
  const errors = error?.data?.errors;
  for (const field of fields) {
    const value = errors?.[field];
    const message = Array.isArray(value) ? value[0] : value;
    if (typeof message === 'string' && message.length <= 300 && !/SQLSTATE|insert into|select .*from|stack trace/i.test(message)) return message;
  }
  return 'Não foi possível salvar o PDV. Confira os dados e tente novamente.';
};
