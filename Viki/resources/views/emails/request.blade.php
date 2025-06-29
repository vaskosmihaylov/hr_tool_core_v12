<table cellspacing="0" cellpadding="0" border="0" style="color:#333;background:#fff;padding:0;margin:0;width:100%;font:15px/1.25em 'Helvetica Neue',Arial,Helvetica">
  <tbody>
    <tr width="100%">
      <td valign="top" align="left" style="background:#eef0f1;font:15px/1.25em 'Helvetica Neue',Arial,Helvetica">
        <table style="border:none;padding:0 18px;margin:50px auto;width:500px">
          <tbody>
            <tr width="100%">
              <td valign="top" align="left" style="background:#fff;padding:18px">

                <h1 style="font-size:20px;margin:16px 0;color:#333;text-align:center"> Здравейте ,</h1>
                <p style="font:15px/1.25em 'Helvetica Neue',Arial,Helvetica;color:#333;text-align:center">
                  Има нужда от {{$data['reason']}} за обекта "{{$data['workerplace']}}" причинен от промените на {{$data['userWhoTriggerChange']}}
                  <br>
                  Моля отидете на този линк : {{$data['link']}} , за да предприемете действие.
				<br> </p>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>